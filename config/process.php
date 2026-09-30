<?php
/**
 * This file is part of webman.
 * Licensed under The MIT License
 * For full copyright and license information, please see the MIT-LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @author    walkor<walkor@workerman.net>
 * @copyright walkor<walkor@workerman.net>
 * @link      http://www.workerman.net/
 * @license   http://www.opensource.org/licenses/mit-license.php MIT License
 */

use app\process\Http;
use support\Log;
use support\Request;

global $argv;

return [
    'webman'            => [
        'handler'     => Http::class,
        'listen'      => 'http://0.0.0.0:8500',
        'count'       => cpu_count() * 4,
        'user'        => '',
        'group'       => '',
        'reusePort'   => false,
        // 协程事件循环（Swow）：阻塞 IO 时自动切换协程，配合 support\Context 实现租户上下文按协程隔离
        // 未安装 swow 扩展时可通过 .env 的 APP_EVENT_LOOP= 置空回退同步模式
        'eventLoop'   => env('APP_EVENT_LOOP', \Workerman\Events\Swow::class) ?: '',
        'context'     => [],
        'constructor' => [
            'requestClass' => Request::class,
            'logger'       => Log::channel('default'),
            'appPath'      => app_path(),
            'publicPath'   => public_path(),
        ],
    ],
    'monitor'           => [
        'handler'     => \app\process\Monitor::class,
        'reloadable'  => false,
        // File update detection and automatic reload
        'constructor' => [
            // Monitor these directories
            'monitorDir'        => array_merge([
                app_path(),
                config_path(),
                base_path() . '/process',
                base_path() . '/support',
                base_path() . '/resource',
                //                base_path() . '/.env',//这里注释避免安装过程中失联
                base_path() . '/core',
            ], glob(base_path() . '/plugin/*/app'), glob(base_path() . '/plugin/*/config'), glob(base_path() . '/plugin/*/api'), glob(base_path() . '/core/*/config')),
            // Files with these suffixes will be monitored
            'monitorExtensions' => [
                'php', 'html', 'htm', 'env',
            ],
            'options'           => [
                'enable_file_monitor'   => !in_array('-d', $argv) && DIRECTORY_SEPARATOR === '/',
                'enable_memory_monitor' => DIRECTORY_SEPARATOR === '/',
            ],
        ],
    ],
    'madong-scheduler'  => [
        'handler' => \core\infrastructure\scheduler\SchedulerServer::class,
        'count'   => 1,
        // 监听端口必须与 Client 连接端口一致：core.infrastructure.scheduler.listen（默认 127.0.0.1:2001）
        'listen'  => 'text://' . config('core.infrastructure.scheduler.listen', '127.0.0.1:2001'),
    ],
    // 插件同步队列消费者
    'plugin-sync-consumer' => [
        'handler'  => \app\process\PluginSyncJobConsumer::class,
        'count'    => 1,
        'reloadable' => false,
        'constructor' => [
            'worker_num' => 1,
        ],
    ],
];
