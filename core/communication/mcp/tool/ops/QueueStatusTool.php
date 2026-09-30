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
use support\Db;
use support\Redis;

/**
 * queue_status：队列积压与消费状态（登录可见）
 *
 * webman/redis-queue 未配置时返回 available=false；已配置时列出各队列的待处理、延迟、失败数量。
 */
final class QueueStatusTool
{
    public function __construct(
        private readonly ?McpUser $user = null,
    ) {
    }

    #[McpTool(
        name: 'queue_status',
        title: 'Queue Status',
        description: '查询 webman/redis-queue 队列状态：各队列待处理/延迟/失败消息数量、消费者进程数。未安装或未配置队列时返回 available=false。',
        inputSchema: ['type' => 'object', 'properties' => []],
        permission: false,
    )]
    public function status(): array
    {
        $configFile = config_path('plugin/webman/redis-queue/redis_queue.php');
        if (!is_file($configFile)) {
            return [
                'available' => false,
                'reason'    => '队列模块未配置：缺少 ' . $configFile . '（执行 php webman webman:install plugin 发布 webman/redis-queue 配置）',
            ];
        }

        $config = config('plugin.webman.redis-queue.redis_queue');
        if (empty($config['queues'])) {
            return ['available' => false, 'reason' => '队列配置中无队列定义'];
        }

        $queues = [];
        foreach ($config['queues'] as $name => $queueConf) {
            $key = is_string($queueConf['key'] ?? null) ? $queueConf['key'] : 'queue-' . $name;
            $queues[$name] = [
                'handler'     => $queueConf['handler'] ?? null,
                'count'       => $queueConf['count'] ?? 1,
                'pending'     => $this->llen($key),
                'delayed'     => $this->zcard($key . '-delayed'),
                'failed'      => $this->llen($key . '-failed'),
            ];
        }

        return [
            'available' => true,
            'queues'    => $queues,
        ];
    }

    private function llen(string $key): int
    {
        try {
            return (int) Redis::llen($key);
        } catch (\Throwable) {
            return -1;
        }
    }

    private function zcard(string $key): int
    {
        try {
            return (int) Redis::zcard($key);
        } catch (\Throwable) {
            return -1;
        }
    }
}
