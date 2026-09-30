<?php

use Webman\Push\Server;

// 注意：不能在此处使用 config('plugin.webman.push.app.*')。
// 项目存在 config/plugin.php（WP4 插件生命周期配置），会遮蔽 plugin.* 目录命名空间，
// 导致本文件在配置加载阶段被 include 时 config() 提前读取返回 null。
// 因此直接读取同级的 app.php，保证 listen/api/app_info 正确解析。
$pushApp = require __DIR__ . '/app.php';

return [
    'server' => [
        'handler'     => Server::class,
        'listen'      => $pushApp['websocket'],
        'count'       => 1, // 必须是1
        'reloadable'  => false, // 执行reload不重启
        'constructor' => [
            'api_listen' => $pushApp['api'],
            'app_info'   => [
                $pushApp['app_key'] => [
                    'channel_hook' => $pushApp['channel_hook'],
                    'app_secret'   => $pushApp['app_secret'],
                ],
            ]
        ]
    ]
];