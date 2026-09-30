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
 * cache_clear：删除指定缓存 key（写操作，权限码 mcp:cache:clear）
 *
 * 安全约束：
 *  - 必须显式传 keys 数组（至少 1 个），不提供全量清理能力
 *  - 单个 key 不允许含通配符 * 或 ?（防误删整库）
 *  - key 自动拼接应用缓存前缀（config cache.custom.prefix）
 */
final class CacheClearTool
{
    public function __construct(
        private readonly ?McpUser $user = null,
    ) {
    }

    #[McpTool(
        name: 'cache_clear',
        title: 'Cache Clear',
        description: '⚠️ 写操作：删除指定缓存 key。必须传 keys 数组（至少 1 个，单个 key 不允许含通配符 * ?），key 会自动拼接应用缓存前缀。不提供全量清理。需要权限码 mcp:cache:clear。',
        inputSchema: [
            'type'       => 'object',
            'properties' => [
                'keys' => [
                    'type'        => 'array',
                    'items'       => ['type' => 'string'],
                    'description' => '要删除的缓存 key 列表（不含前缀，如 user:info:1）',
                    'minItems'    => 1,
                ],
            ],
            'required' => ['keys'],
        ],
        permission: 'mcp:cache:clear',
    )]
    public function clear(array $keys): array
    {
        if ($keys === []) {
            return ['deleted' => 0, 'message' => 'keys 不能为空'];
        }

        $prefix = (string) config('cache.custom.prefix', '');
        $invalid = [];
        $fullKeys = [];

        foreach ($keys as $key) {
            if (!is_string($key) || $key === '') {
                $invalid[] = (string) $key;
                continue;
            }
            // 禁止通配符
            if (strpbrk($key, '*?') !== false) {
                $invalid[] = $key . ' (含通配符)';
                continue;
            }
            $fullKeys[] = $prefix . $key;
        }

        if ($fullKeys === []) {
            return ['deleted' => 0, 'invalid' => $invalid, 'message' => '没有合法的 key 可删除'];
        }

        try {
            $deleted = Redis::del(...$fullKeys);
        } catch (\Throwable $e) {
            return ['deleted' => 0, 'message' => 'Redis 操作失败：' . $e->getMessage()];
        }

        return [
            'deleted'     => $deleted,
            'requested'   => count($fullKeys),
            'invalid'     => $invalid,
            'prefix'      => $prefix,
            'operator_id' => $this->user?->id,
            'message'     => $deleted > 0 ? "已删除 {$deleted} 个缓存 key" : '指定 key 均不存在或已过期',
        ];
    }
}
