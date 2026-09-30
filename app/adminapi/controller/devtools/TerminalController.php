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

namespace app\adminapi\controller\devtools;

use app\adminapi\middleware\AccessTokenMiddleware;
use app\adminapi\middleware\OperationMiddleware;
use app\adminapi\middleware\PermissionMiddleware;
use core\business\terminal\Terminal;
use core\foundation\tool\Json;
use core\foundation\tool\Sse;
use madong\swagger\annotation\response\SimpleResponse;
use madong\swagger\attribute\AllowAnonymous;
use madong\swagger\attribute\Permission;
use OpenApi\Attributes as OA;
use support\Request;
use support\Response;
use support\annotation\Middleware;

#[Middleware(AccessTokenMiddleware::class, PermissionMiddleware::class, OperationMiddleware::class)]
final class TerminalController
{

    #[OA\Get(
        path: "/terminal/config",
        summary: "获取终端配置",
        tags: ["终端管理"]
    )]
    #[Permission(code: "terminal:config:read")]
    #[AllowAnonymous(requireToken: false, requirePermission: false)]
    #[SimpleResponse(schema: [], example: [
        'enabled'             => true,
        'npm_package_manager' => 'pnpm',
        'npm_registry'        => 'npm',
        'composer_registry'   => 'composer',
        'package_managers'    => [
            'npm'  => [
                'name'    => 'NPM',
                'install' => 'npm install',
                'build'   => 'npm run build',
                'check'   => 'npm --version',
            ],
            'cnpm' => [
                'name'    => 'CNPM',
                'install' => 'cnpm install',
                'build'   => 'cnpm run build',
                'check'   => 'cnpm --version',
            ],
            'pnpm' => [
                'name'    => 'PNPM',
                'install' => 'pnpm install',
                'build'   => 'pnpm run build',
                'check'   => 'pnpm --version',
            ],
            'yarn' => [
                'name'    => 'YARN',
                'install' => 'yarn install',
                'build'   => 'yarn build',
                'check'   => 'yarn --version',
            ],
        ],
        'frontend_programs'   => [
            'admin'   => [
                'enabled'        => true,
                'source_dir'     => '{project_root}/admin/dist',
                'target_dir'     => '{backend_root}/public/admin',
                'copy_mappings'  => [
                    '*' => '.',
                ],
                'clean_target'   => true,
                'preserve_files' => [
                    '.gitkeep',
                ],
                'copy_options'   => [
                    'recursive'            => true,
                    'overwrite'            => true,
                    'preserve_permissions' => true,
                ],
            ],
            'web'     => [
                'enabled'        => true,
                'source_dir'     => '{project_root}/web/.output/public',
                'target_dir'     => '{backend_root}/public',
                'copy_mappings'  => [
                    '*' => '.',
                ],
                'clean_target'   => true,
                'preserve_files' => [
                    '.gitkeep',
                ],
                'copy_options'   => [
                    'recursive'            => true,
                    'overwrite'            => true,
                    'preserve_permissions' => true,
                ],
            ],
            'h5'      => [
                'enabled'        => true,
                'source_dir'     => '{project_root}/uni-app/dist/build/h5',
                'target_dir'     => '{backend_root}/public/h5',
                'copy_mappings'  => [
                    '*' => '.',
                ],
                'clean_target'   => true,
                'preserve_files' => [
                    '.gitkeep',
                ],
                'copy_options'   => [
                    'recursive'            => true,
                    'overwrite'            => true,
                    'preserve_permissions' => true,
                ],
            ],
            'app'     => [
                'enabled'        => true,
                'source_dir'     => '{project_root}/uni-app/dist/build/app',
                'target_dir'     => '{backend_root}/public/app',
                'copy_mappings'  => [
                    '*' => '.',
                ],
                'clean_target'   => true,
                'preserve_files' => [
                    '.gitkeep',
                ],
                'copy_options'   => [
                    'recursive'            => true,
                    'overwrite'            => true,
                    'preserve_permissions' => true,
                ],
            ],
            'uni-app' => [
                'enabled'          => true,
                'platforms'        => [
                    'h5'        => [
                        'source_dir'    => '{project_root}/uni-app/dist/build/h5',
                        'target_dir'    => '{backend_root}/public/uni-app/h5',
                        'copy_mappings' => [
                            '*' => '.',
                        ],
                    ],
                    'mp-weixin' => [
                        'source_dir'    => '{project_root}/uni-app/dist/build/mp-weixin',
                        'target_dir'    => '{backend_root}/public/uni-app/mp-weixin',
                        'copy_mappings' => [
                            '*' => '.',
                        ],
                    ],
                    'app-plus'  => [
                        'source_dir'    => '{project_root}/uni-app/dist/build/app-plus',
                        'target_dir'    => '{backend_root}/public/uni-app/app-plus',
                        'copy_mappings' => [
                            '*' => '.',
                        ],
                    ],
                ],
                'default_platform' => 'h5',
                'clean_target'     => true,
                'preserve_files'   => [
                    '.gitkeep',
                ],
                'copy_options'     => [
                    'recursive'            => true,
                    'overwrite'            => true,
                    'preserve_permissions' => true,
                ],
            ],
        ],
    ])]
    public function config(Request $request): Response
    {
        try {
            $config = [
                'enabled'             => config('terminal.enabled', false),
                'npm_package_manager' => config('terminal.npm_package_manager', 'npm'),
                'npm_registry'        => config('terminal.registries.npm.current', 'npm'),
                'composer_registry'   => config('terminal.registries.composer.current', 'composer'),
                'package_managers'    => config('terminal.package_managers', []),
                'frontend_programs'   => config('terminal.frontend_programs', []),
            ];

            return Json::success('获取配置成功', $config);
        } catch (\Exception $e) {
            return Json::fail($e->getMessage());
        }
    }

    #[OA\Post(
        path: "/terminal/config",
        summary: "更新终端配置",
        tags: ["终端管理"]
    )]
    #[Permission(code: "terminal:config:create")]
    #[SimpleResponse(schema: [], example: [])]
    public function updateConfig(Request $request): Response
    {
        try {
            $data = $request->post();

            // 读取当前配置
            $configFile = base_path() . '/config/terminal.php';
            $config     = require $configFile;

            // 更新包管理器配置
            if (isset($data['npm_package_manager'])) {
                $config['npm_package_manager'] = $data['npm_package_manager'];
            }

            // 保存配置到文件
            $content = "<?php\n\nreturn " . $this->arrayToPhpConfig($config) . ";\n";
            file_put_contents($configFile, $content);

            return Json::success('配置更新成功');
        } catch (\Exception $e) {
            return Json::fail($e->getMessage());
        }
    }

    #[OA\Get(
        path: "/terminal/commands",
        summary: "获取命令列表（带分组）",
        tags: ["终端管理"]
    )]
    #[Permission(code: "terminal:config:commands")]
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
        path: "/terminal",
        summary: "执行命令（SSE方式）",
        tags: ["终端管理"]
    )]
    #[OA\Parameter(
        name: "command",
        description: "命令key",
        in: "query",
        required: true,
        schema: new OA\Schema(type: "string"),
    )]
    #[OA\Parameter(
        name: "uuid",
        description: "会话UUID",
        in: "query",
        required: true,
        schema: new OA\Schema(type: "string"),
    )]
    #[OA\Parameter(
        name: "extend",
        description: "扩展信息",
        in: "query",
        required: false,
        schema: new OA\Schema(type: "string"),
    )]
    #[Permission(code: "terminal:exec")]
    #[SimpleResponse(schema: [], example: [])]
    public function exec(Request $request): void
    {
        $connection = $request->connection;
        $command    = $request->input('command');
        $uuid       = $request->input('uuid', '');
        $extend     = $request->input('extend', '');

        // 发送SSE响应头
        $connection->send(new Response(200, [
            'Content-Type'                     => 'text/event-stream',
            'Cache-Control'                    => 'no-cache',
            'Access-Control-Allow-Origin'      => '*',
            'Access-Control-Allow-Credentials' => 'true',
            'Access-Control-Expose-Headers'    => 'Content-Type',
            'X-Accel-Buffering'                => 'no',
        ], "\r\n"));

        // 验证参数
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
        path: "/terminal/execute",
        summary: "执行命令（简单方式）",
        tags: ["终端管理"]
    )]
    #[OA\Parameter(
        name: "command_key",
        description: "命令key",
        in: "query",
        required: true,
        schema: new OA\Schema(type: "string"),
    )]
    #[OA\Parameter(
        name: "variables",
        description: "命令变量",
        in: "query",
        required: false,
        schema: new OA\Schema(type: "object"),
    )]
    #[OA\Parameter(
        name: "session_uuid",
        description: "会话UUID",
        in: "query",
        required: false,
        schema: new OA\Schema(type: "string"),
    )]
    #[Permission(code: "terminal:execute")]
    #[SimpleResponse(schema: [], example: [])]
    public function execute(Request $request): Response
    {
        try {
            $commandKey  = $request->post('command_key');
            $variables   = $request->post('variables', []);
            $sessionUuid = $request->post('session_uuid', '');

            if (!$commandKey) {
                return Json::fail('命令不能为空');
            }

            $terminal = Terminal::create($sessionUuid, '');
            $result   = $terminal->executeSimple($commandKey, $variables, $sessionUuid);

            return Json::success($result['message'], $result);
        } catch (\Exception $e) {
            return Json::fail($e->getMessage());
        }
    }

    /**
     * 将数组转换为PHP配置字符串
     *
     * @param array $array
     * @param int   $indent
     *
     * @return string
     */
    private function arrayToPhpConfig(array $array, int $indent = 0): string
    {
        $spaces = str_repeat('    ', $indent);
        $result = "[\n";

        foreach ($array as $key => $value) {
            $result .= $spaces . "    ";

            // 处理键名
            if (is_string($key)) {
                $result .= "'" . str_replace("'", "\\'", $key) . "' => ";
            } else {
                $result .= $key . " => ";
            }

            // 处理值
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