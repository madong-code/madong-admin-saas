<?php

declare(strict_types=1);

/**
 *+------------------
 * madong
 *+------------------
 * Copyright (c) https://gitee.com/motion-code  All rights reserved.
 *+------------------
 * Author: Mr. April (405784684@qq.com)
 *+------------------
 * Official Website: http://www.madong.tech
 */

namespace app\platform\controller\plugin;

use app\platform\controller\Base;
use core\foundation\tool\Json;
use madong\swagger\annotation\response\SimpleResponse;
use madong\swagger\attribute\Permission;
use OpenApi\Attributes as OA;
use support\annotation\Middleware;
use support\Request;

#[OA\Tag(name: '平台-插件安装', description: '平台端应用管理-远程安装插件(走 SSE)')]
#[Middleware(\app\platform\middleware\AccessTokenMiddleware::class)]
final class PluginInstallController extends Base
{
    #[OA\Post(
        path: '/plugin/install',
        summary: '远程安装/更新插件(走 SSE 实时输出进度)',
        tags: ['平台-插件安装'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'application/json',
                schema: new OA\Schema(
                    properties: [
                        new OA\Property(property: 'name', description: '插件标识(plugin key)', type: 'string'),
                        new OA\Property(property: 'mode', description: '安装模式: local=本地包, market=远程市场', type: 'string', enum: ['local', 'market']),
                        new OA\Property(property: 'version', description: '目标版本(market 模式必填)', type: 'string'),
                    ]
                )
            )
        )
    )]
    #[Permission('platform:plugin:install')]
    #[SimpleResponse(example: '{"code":0,"msg":"ok","data":{"stage":"done"}}')]
    public function install(Request $request): \support\Response
    {
        $name    = trim((string)$request->input('name', ''));
        $mode    = (string)$request->input('mode', 'local');
        $version = (string)$request->input('version', '');

        if ($name === '') {
            return Json::fail('插件标识不能为空');
        }
        if (!in_array($mode, ['local', 'market'], true)) {
            return Json::fail('模式必须是 local 或 market');
        }
        if ($mode === 'market' && $version === '') {
            return Json::fail('market 模式必须指定目标版本');
        }

        // 关闭 HTTP 长连接缓冲,改为 SSE
        $response = response();
        $response->header('Content-Type', 'text/event-stream');
        $response->header('Cache-Control', 'no-cache');
        $response->header('X-Accel-Buffering', 'no');

        // 关闭 webman 静态文件直接输出模式,确保 SSE flush
        $GLOBALS['__webman_sse_streaming'] = true;

        $payload = [
            'name'    => $name,
            'mode'    => $mode,
            'version' => $version,
        ];

        // 1) 推送开始事件
        $this->sseEmit($response, 'start', 0, $payload);

        try {
            // 2) 调用 webman console: php webman madong-plugin:install <name> <mode>
            //  - mode=local   : 安装本地包 (backend/plugin/{name})
            //  - mode=market  : 从远程市场拉取并安装
            $cmd = sprintf(
                'cd %s && php webman madong-plugin:install %s %s 2>&1',
                escapeshellarg(base_path()),
                escapeshellarg($name),
                escapeshellarg($mode),
            );

            $this->sseEmit($response, 'progress', 10, ['msg' => '已启动安装进程 ...']);

            $proc = @proc_open($cmd, [
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ], $pipes);

            if (!is_resource($proc)) {
                $this->sseEmit($response, 'error', 100, ['msg' => '无法启动子进程: proc_open 不可用']);
                return $response;
            }

            // 3) 实时读取 stdout/stderr
            stream_set_blocking($pipes[1], false);
            stream_set_blocking($pipes[2], false);

            $pct = 20;
            $lastOutputAt = time();
            while (true) {
                $stdout = stream_get_contents($pipes[1]);
                $stderr = stream_get_contents($pipes[2]);
                $chunk  = trim(($stdout ?: '') . ($stderr ? "\n" . $stderr : ''));

                if ($chunk !== '') {
                    $this->sseEmit($response, 'progress', min($pct, 90), ['msg' => $chunk]);
                    $pct += 5;
                    $lastOutputAt = time();
                }

                $status = proc_get_status($proc);
                if (!$status['running']) {
                    // 排空剩余输出
                    $remStdout = (string)stream_get_contents($pipes[1]);
                    $remStderr = (string)stream_get_contents($pipes[2]);
                    if ($remStdout !== '' || $remStderr !== '') {
                        $this->sseEmit($response, 'progress', 95, ['msg' => trim($remStdout . "\n" . $remStderr)]);
                    }
                    fclose($pipes[1]);
                    fclose($pipes[2]);
                    $exitCode = proc_close($proc);
                    break;
                }

                // 心跳: 5s 无输出保活
                if (time() - $lastOutputAt > 5) {
                    $this->sseEmit($response, 'heartbeat', $pct, ['msg' => '...']);
                    $lastOutputAt = time();
                }

                usleep(200_000); // 200ms
            }

            if ($exitCode === 0) {
                $this->sseEmit($response, 'done', 100, [
                    'name'    => $name,
                    'mode'    => $mode,
                    'version' => $version,
                    'msg'     => '安装成功',
                ]);
            } else {
                $this->sseEmit($response, 'error', 100, [
                    'msg'      => '安装失败',
                    'exitCode' => $exitCode,
                ]);
            }
        } catch (\Throwable $e) {
            $this->sseEmit($response, 'error', 100, ['msg' => '异常: ' . $e->getMessage()]);
        }

        return $response;
    }

    /**
     * 推送一条 SSE 事件
     */
    private function sseEmit(\support\Response $response, string $event, int $pct, array $data): void
    {
        $payload = json_encode([
            'event' => $event,
            'pct'   => $pct,
            'data'  => $data,
        ], JSON_UNESCAPED_UNICODE);
        $response->write("event: {$event}\ndata: {$payload}\n\n");
        if (function_exists('fastcgi_finish_request')) {
            @ob_flush();
            @flush();
        }
    }
}
