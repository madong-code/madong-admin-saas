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
use core\infrastructure\monitor\ServerMonitor;

/**
 * server_monitor：服务器资源巡检（复用 core/infrastructure/monitor/ServerMonitor）
 *
 * 每个 section 独立捕获异常（如 Redis 不可达不影响其他分区）。
 */
final class ServerMonitorTool
{
    public function __construct(
        private readonly ?McpUser $user = null,
    ) {
    }

    #[McpTool(
        name: 'server_monitor',
        title: 'Server Monitor',
        description: '查询 madong 服务器资源信息：disk 磁盘 / cpu 处理器 / memory 内存 / redis 连接与命中率（不含键数据）/ php 运行环境，overview 汇总概要。',
        inputSchema: [
            'type' => 'object',
            'properties' => [
                'section' => [
                    'type' => 'string',
                    'enum' => ['overview', 'disk', 'cpu', 'memory', 'redis', 'php'],
                    'default' => 'overview',
                    'description' => '信息分区',
                ],
            ],
        ],
        permission: false,
    )]
    public function serverMonitor(string $section = 'overview'): array
    {
        $monitor = new ServerMonitor();

        return match ($section) {
            'disk'   => $this->wrap('disk', fn (): array => $monitor->getDiskInfo()),
            'cpu'    => $this->wrap('cpu', fn (): array => $monitor->getCpuInfo()),
            'memory' => $this->wrap('memory', fn (): array => $monitor->getMemoryInfo()),
            'php'    => $this->wrap('php', fn (): array => $monitor->getPhpInfo()),
            'redis'  => $this->wrap('redis', fn (): array => $this->redisInfo()),
            default  => [
                'php'    => $this->wrap('php', fn (): array => $monitor->getPhpInfo()),
                'memory' => $this->wrap('memory', fn (): array => $monitor->getMemoryInfo()),
                'cpu'    => $this->wrap('cpu', fn (): array => $monitor->getCpuInfo()),
                'disk'   => $this->wrap('disk', fn (): array => $monitor->getDiskInfo()),
                'redis'  => $this->wrap('redis', fn (): array => $this->redisInfo()),
            ],
        };
    }

    /**
     * 分区包装：单分区失败不影响整体，错误以 error 字段返回
     */
    private function wrap(string $section, callable $loader): array
    {
        try {
            return ['section' => $section, 'data' => $loader()];
        } catch (\Throwable $e) {
            return ['section' => $section, 'error' => $e->getMessage()];
        }
    }

    /**
     * 轻量 Redis 信息：仅运行指标，不扫描键数据（区别于 ServerMonitor::getRedisInfo 全量扫描）
     */
    private function redisInfo(): array
    {
        $config = (array) config('redis.default', []);
        if ($config === []) {
            return ['enabled' => false];
        }

        $redis = new \Redis();
        $redis->connect((string) $config['host'], (int) $config['port']);
        if (!empty($config['password'])) {
            $redis->auth((string) $config['password']);
        }
        $info = $redis->info();
        $redis->close();

        $hits   = (int) ($info['keyspace_hits'] ?? 0);
        $misses = (int) ($info['keyspace_misses'] ?? 0);
        $total  = $hits + $misses;

        return [
            'uptime_in_seconds' => (int) ($info['uptime_in_seconds'] ?? 0),
            'connected_clients' => (int) ($info['connected_clients'] ?? 0),
            'used_memory'       => $info['used_memory_human'] ?? (string) ($info['used_memory'] ?? '0'),
            'hit_rate'          => $total > 0 ? round($hits / $total * 100, 2) . '%' : 'N/A',
            'maxmemory_policy'  => $info['maxmemory_policy'] ?? '',
        ];
    }
}
