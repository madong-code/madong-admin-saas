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

namespace core\communication\mcp\tool\system;

use core\communication\mcp\attribute\McpTool;
use core\communication\mcp\security\McpUser;
use core\communication\mcp\support\McpTenant;

/**
 * scheduler_status：定时任务调度器状态（登录可见）
 *
 * 返回：
 *  - scheduler 进程健康（端口 2001 探测）
 *  - sys_crontab 任务清单（id/title/type/enabled/singleton）
 *  - sys_crontab_log 最近 N 条（code/log/running_time）
 *
 * 未启用调度器时返回 available=false
 */
final class SchedulerStatusTool
{
    public function __construct(
        private readonly ?McpUser $user = null,
    ) {
    }

    #[McpTool(
        name: 'scheduler_status',
        title: 'Scheduler Status',
        description: '定时任务调度器状态：进程健康（端口探测）、sys_crontab 任务清单、sys_crontab_log 最近执行记录。未启动调度器进程时 returned available=false。',
        inputSchema: [
            'type'       => 'object',
            'properties' => [
                'log_limit' => [
                    'type'        => 'integer',
                    'description' => '返回最近几条执行日志，默认 20，最大 50',
                ],
            ],
        ],
        permission: false,
    )]
    public function status(int $log_limit = 20): array
    {
        $log_limit = min(max($log_limit, 1), 50);

        $listen = (string) config('core.infrastructure.scheduler.listen', '127.0.0.1:2001');
        $errno = 0;
        $errstr = '';
        $socket = @stream_socket_client('tcp://' . $listen, $errno, $errstr, 1);
        $alive = $socket !== false;
        if ($socket) {
            fclose($socket);
        }

        // 任务清单
        try {
            $tasks = McpTenant::table('sys_crontab')
                ->orderByDesc('id')
                ->limit(100)
                ->get(['id', 'title', 'type', 'enabled', 'singleton', 'last_running_time'])
                ->map(fn ($r) => (array) $r)
                ->toArray();

            $taskCount = McpTenant::table('sys_crontab')->count();
            $enabledCount = McpTenant::table('sys_crontab')->where('enabled', 1)->count();
        } catch (\Throwable $e) {
            return [
                'available'   => false,
                'scheduler'   => ['alive' => $alive, 'listen' => $listen, 'detail' => $alive ? '进程运行中' : '进程不可达：' . $errstr],
                'reason'      => 'sys_crontab 表不可用：' . $e->getMessage(),
            ];
        }

        // 最近执行日志
        try {
            $logs = McpTenant::table('sys_crontab_log')
                ->orderByDesc('id')
                ->limit($log_limit)
                ->get(['id', 'crontab_id', 'return_code', 'running_time', 'log', 'created_at'])
                ->map(fn ($r) => (array) $r)
                ->toArray();
        } catch (\Throwable) {
            $logs = [];
        }

        return [
            'available' => true,
            'scheduler' => [
                'alive'  => $alive,
                'listen' => $listen,
                'detail' => $alive ? '进程运行中' : '进程不可达：' . $errstr,
            ],
            'tasks' => [
                'total'   => $taskCount,
                'enabled' => $enabledCount,
                'items'   => $tasks,
            ],
            'recent_logs' => $logs,
        ];
    }
}
