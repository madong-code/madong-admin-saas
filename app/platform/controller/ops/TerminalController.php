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
namespace app\platform\controller\ops;

use app\platform\middleware\AccessTokenMiddleware;
use core\business\terminal\Terminal;
use core\foundation\tool\Json;
use core\foundation\tool\Sse;
use madong\swagger\annotation\response\SimpleResponse;
use madong\swagger\attribute\AllowAnonymous;
use madong\swagger\attribute\Permission;
use OpenApi\Attributes as OA;
use support\annotation\Middleware;
use support\Request;
use support\Response;

#[Middleware(AccessTokenMiddleware::class)]
final class TerminalController
{
    #[OA\Get(
        path: '/terminal/config',
        summary: '获取终端配置',
        tags: ['终端管理']
    )]
    #[Permission(code: 'platform:terminal:config:read')]
    #[AllowAnonymous(requireToken: false, requirePermission: false)]
    #[SimpleResponse(schema: [], example: [
        'enabled' => true,
        'npm_package_manager' => 'pnpm',
        'npm_registry' => 'npm',
        'composer_registry' => 'composer',
        'package_managers' => [
            'npm' => ['name' => 'NPM', 'install' => 'npm install', 'build' => 'npm run build', 'check' => 'npm --version'],
            'cnpm' => ['name' => 'CNPM', 'install' => 'cnpm install', 'build' => 'cnpm run build', 'check' => 'cnpm --version'],
            'pnpm' => ['name' => 'PNPM', 'install' => 'pnpm install', 'build' => 'pnpm run build', 'check' => 'pnpm --version'],
            'yarn' => ['name' => 'YARN', 'install' => 'yarn install', 'build' => 'yarn build', 'check' => 'yarn --version'],
        ],
        'frontend_programs' => [
            'admin' => ['enabled' => true, 'source_dir' => '{project_root}/admin/dist', 'target_dir' => '{backend_root}/public/admin', 'copy_mappings' => ['*' => '.'], 'clean_target' => true, 'preserve_files' => ['.gitkeep'], 'copy_options' => ['recursive' => true, 'overwrite' => true, 'preserve_permissions' => true]],
        ],
    ])]
    public function config(Request $request): Response
    {
        try {
            $config = [
                'enabled' => config('terminal.enabled', false),
                'npm_package_manager' => config('terminal.npm_package_manager', 'npm'),
                'npm_registry' => config('terminal.registries.npm.current', 'npm'),
                'composer_registry' => config('terminal.registries.composer.current', 'composer'),
                'package_managers' => config('terminal.package_managers', []),
                'frontend_programs' => config('terminal.frontend_programs', []),
            ];
            return Json::success('获取配置成功', $config);
        } catch (\Exception $e) {
            return Json::fail($e->getMessage());
        }
    }

    #[OA\Post(
        path: '/terminal/config',
        summary: '更新终端配置',
        tags: ['终端管理']
    )]
    #[Permission(code: 'platform:terminal:config:create')]
    #[SimpleResponse(schema: [], example: [])]
    public function updateConfig(Request $request): Response
    {
        try {
            $data = $request->post();
            $configFile = base_path() . '/config/terminal.php';
            $config = require $configFile;

            if (isset($data['npm_package_manager'])) {
                $config['npm_package_manager'] = $data['npm_package_manager'];
            }

            $content = "<?php\n\nreturn " . $this->arrayToPhpConfig($config) . ";\n";
            file_put_contents($configFile, $content);

            return Json::success('配置更新成功');
        } catch (\Exception $e) {
            return Json::fail($e->getMessage());
        }
    }

    #[OA\Get(
        path: '/terminal/commands',
        summary: '获取命令列表（带分组）',
        tags: ['终端管理']
    )]
    #[Permission(code: 'platform:terminal:config:commands')]
    #[AllowAnonymous(requireToken: false, requirePermission: false)]
    #[SimpleResponse(schema: [], example: [])]
    public function commands(Request $request): Response
    {
        try {
            $webConfig = config('terminal.web.command_groups', []);
            return Json::success('获取命令列表成功', $webConfig);
        } catch (\Exception $e) {
            return Json::fail($e->getMessage());
        }
    }

    #[OA\Get(
        path: '/terminal',
        summary: '执行命令（SSE方式）',
        tags: ['终端管理']
    )]
    #[OA\Parameter(name: 'command', description: '命令key', in: 'query', required: true, schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(name: 'uuid', description: '会话UUID', in: 'query', required: true, schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(name: 'extend', description: '扩展信息', in: 'query', schema: new OA\Schema(type: 'string'))]
    #[Permission(code: 'platform:terminal:exec')]
    #[SimpleResponse(schema: [], example: [])]
    public function exec(Request $request): void
    {
        $connection = $request->connection;
        $command = $request->input('command');
        $uuid = $request->input('uuid', '');
        $extend = $request->input('extend', '');

        $connection->send(new Response(200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Access-Control-Allow-Origin' => '*',
            'Access-Control-Allow-Credentials' => 'true',
            'Access-Control-Expose-Headers' => 'Content-Type',
            'X-Accel-Buffering' => 'no',
        ], "\r\n"));

        if (empty($command) || empty($uuid)) {
            $connection->send(Sse::error('参数错误：缺少command或uuid参数', null, $uuid));
            return;
        }

        try {
            $terminal = Terminal::create($uuid, $extend);
            foreach ($terminal->exec($command) as $sseMessage) {
                $connection->send($sseMessage);
            }
        } catch (\Exception $e) {
            $connection->send(Sse::error('执行失败：' . $e->getMessage(), null, $uuid));
        }
    }

    #[OA\Post(
        path: '/terminal/execute',
        summary: '执行命令（简单方式）',
        tags: ['终端管理']
    )]
    #[OA\Parameter(name: 'command_key', description: '命令key', in: 'query', required: true, schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(name: 'variables', description: '命令变量', in: 'query', schema: new OA\Schema(type: 'object'))]
    #[OA\Parameter(name: 'session_uuid', description: '会话UUID', in: 'query', schema: new OA\Schema(type: 'string'))]
    #[Permission(code: 'platform:terminal:execute')]
    #[SimpleResponse(schema: [], example: [])]
    public function execute(Request $request): Response
    {
        try {
            $commandKey = $request->post('command_key');
            $variables = $request->post('variables', []);
            $sessionUuid = $request->post('session_uuid', '');

            if (!$commandKey) {
                return Json::fail('命令不能为空');
            }

            $terminal = Terminal::create($sessionUuid, '');
            $result = $terminal->executeSimple($commandKey, $variables, $sessionUuid);

            return Json::success($result['message'], $result);
        } catch (\Exception $e) {
            return Json::fail($e->getMessage());
        }
    }

    private function arrayToPhpConfig(array $array, int $indent = 0): string
    {
        $spaces = str_repeat('    ', $indent);
        $result = "[\n";

        foreach ($array as $key => $value) {
            $result .= $spaces . "    ";
            if (is_string($key)) {
                $result .= "'" . str_replace("'", "\\'", $key) . "' => ";
            } else {
                $result .= $key . " => ";
            }
            if (is_array($value)) {
                $result .= $this->arrayToPhpConfig($value, $indent + 1);
            } elseif (is_string($value)) {
                $result .= "'" . str_replace("'", "\\'", $value) . "'";
            } elseif (is_bool($value)) {
                $result .= $value ? 'true' : 'false';
            } elseif (is_null($value)) {
                $result .= 'null';
            } else {
                $result .= $value;
            }
            $result .= ",\n";
        }

        $result .= $spaces . "]";
        return $result;
    }
}
