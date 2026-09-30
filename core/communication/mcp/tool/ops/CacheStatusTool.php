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
use support\Redis;

/**
 * cache_status：缓存状态查询（登录可见）
 *
 * 聚焦缓存维度（key 数量、内存、命中率、各库分布），区别于 server_monitor 的 Redis 运行指标。
 */
final class CacheStatusTool
{
    public function __construct(
        private readonly ?McpUser $user = null,
    ) {
    }

    #[McpTool(
        name: 'cache_status',
        title: 'Cache Status',
        description: '查询 madong 缓存状态：key 总数、内存占用、命中率、各 DB keyspace 分布、最近慢命令数。只读，不返回任何 key 的值。',
        inputSchema: ['type' => 'object', 'properties' => []],
        permission: false,
    )]
    public function status(): array
    {
        try {
            $info = Redis::info();
        } catch (\Throwable $e) {
            return ['status' => 'degraded', 'detail' => 'Redis 不可用：' . $e->getMessage()];
        }

        $hits   = (int) ($info['keyspace_hits'] ?? 0);
        $misses = (int) ($info['keyspace_misses'] ?? 0);
        $total  = $hits + $misses;

        // 聚合所有 db 的 key 数（info() 返回的 dbN 是字符串 "keys=N,expires=N,avg_ttl=N"）
        $keyspace = [];
        $totalKeys = 0;
        foreach ($info as $key => $value) {
            if (preg_match('/^db(\d+)$/', $key, $m)) {
                $parsed = [];
                if (is_array($value)) {
                    $parsed = $value;
                } elseif (is_string($value)) {
                    parse_str(str_replace(',', '&', $value), $parsed);
                }
                $count = (int) ($parsed['keys'] ?? 0);
                $keyspace[$m[1]] = [
                    'keys'    => $count,
                    'expires' => (int) ($parsed['expires'] ?? 0),
                    'avg_ttl' => (int) ($parsed['avg_ttl'] ?? 0),
                ];
                $totalKeys += $count;
            }
        }

        return [
            'status'           => 'healthy',
            'driver'           => (string) config('cache.custom.type', 'redis'),
            'prefix'           => (string) config('cache.custom.prefix', ''),
            'total_keys'       => $totalKeys,
            'memory'           => [
                'used'      => $info['used_memory_human'] ?? (string) ($info['used_memory'] ?? '0'),
                'peak'      => $info['used_memory_peak_human'] ?? null,
                'policy'    => $info['maxmemory_policy'] ?? '',
                'fragmentation' => $info['mem_fragmentation_ratio'] ?? null,
            ],
            'hit_rate'         => $total > 0 ? round($hits / $total * 100, 2) . '%' : 'N/A',
            'hits'             => $hits,
            'misses'           => $misses,
            'connected_clients'=> (int) ($info['connected_clients'] ?? 0),
            'uptime_seconds'   => (int) ($info['uptime_in_seconds'] ?? 0),
            'keyspace'         => $keyspace,
        ];
    }
}
