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

namespace core\communication\mcp\tool\ops;

use core\communication\mcp\attribute\McpTool;
use core\communication\mcp\security\McpUser;
use core\communication\mcp\support\McpTenant;
use support\Redis;

/**
 * health_check：应用依赖体检（登录可见）
 *
 * 检查 DB / Redis / 定时任务调度器 / 队列 四类核心依赖的可用性，
 * 返回 healthy / degraded 状态与原因。区别于 server_monitor（硬件资源指标）。
 */
final class HealthCheckTool
{
    /** scheduler 连接超时（秒） */
    private const SCHEDULER_TIMEOUT = 2;

    public function __construct(
        private readonly ?McpUser $user = null,
    ) {
    }

    #[McpTool(
        name: 'health_check',
        title: 'Health Check',
        description: '应用依赖体检：数据库连接、Redis 连接、定时任务调度器进程、队列模块可用性。返回各组件 healthy/degraded 状态与原因，用于快速定位服务依赖故障（非硬件资源，硬件指标用 server_monitor）。',
        inputSchema: ['type' => 'object', 'properties' => []],
        permission: false,
    )]
    public function check(): array
    {
        return [
            'database'  => $this->checkDatabase(),
            'redis'     => $this->checkRedis(),
            'scheduler' => $this->checkScheduler(),
            'queue'     => $this->checkQueue(),
        ];
    }

    private function checkDatabase(): array
    {
        try {
            $result = McpTenant::connection()->select('SELECT 1 AS ok');
            return [
                'status' => 'healthy',
                'detail' => 'SELECT 1 成功',
                'ok'     => (int) ($result[0]->ok ?? 0) === 1,
            ];
        } catch (\Throwable $e) {
            return ['status' => 'degraded', 'detail' => '数据库连接失败：' . $e->getMessage()];
        }
    }

    private function checkRedis(): array
    {
        try {
            $ping = Redis::ping();
            return [
                'status' => 'healthy',
                'detail' => 'PING 成功',
                'pong'   => $ping,
            ];
        } catch (\Throwable $e) {
            return ['status' => 'degraded', 'detail' => 'Redis 连接失败：' . $e->getMessage()];
        }
    }

    /**
     * 定时任务调度器：尝试连接 scheduler 监听端口（127.0.0.1:2001）
     */
    private function checkScheduler(): array
    {
        $listen = (string) config('core.infrastructure.scheduler.listen', '127.0.0.1:2001');
        $address = 'tcp://' . $listen;

        $errno = 0;
        $errstr = '';
        $socket = @stream_socket_client($address, $errno, $errstr, self::SCHEDULER_TIMEOUT);
        if ($socket === false) {
            return [
                'status' => 'degraded',
                'detail' => '调度器进程未运行或端口不可达：' . $errstr . " ({$listen})",
            ];
        }
        fclose($socket);

        return [
            'status' => 'healthy',
            'detail' => '调度器进程运行中',
            'listen' => $listen,
        ];
    }

    /**
     * 队列模块：检测 webman/redis-queue 配置是否发布
     */
    private function checkQueue(): array
    {
        $configFile = config_path('plugin/webman/redis-queue/redis_queue.php');
        if (!is_file($configFile)) {
            return [
                'status'   => 'degraded',
                'detail'   => '队列模块未配置：缺少 ' . $configFile,
                'enabled'  => false,
                'queue'    => 'redis-queue',
            ];
        }

        $queueConfig = config('plugin.webman.redis-queue.redis_queue');
        if (empty($queueConfig)) {
            return ['status' => 'degraded', 'detail' => '队列配置为空', 'enabled' => false];
        }

        return [
            'status'  => 'healthy',
            'detail'  => '队列模块已配置',
            'enabled' => true,
            'queues'  => array_keys($queueConfig['queues'] ?? []),
        ];
    }
}
