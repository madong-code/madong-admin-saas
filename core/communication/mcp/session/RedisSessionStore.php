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

namespace core\communication\mcp\session;

use Mcp\Server\Session\SessionStoreInterface;
use support\Redis;
use Symfony\Component\Uid\Uuid;

/**
 * MCP 会话 Redis 存储实现（config session.store=redis 时启用）
 *
 * 依赖 Redis TTL 自动过期，gc() 无需扫描；多 worker 共享会话。
 */
final class RedisSessionStore implements SessionStoreInterface
{
    private const KEY_PREFIX = 'mcp:session:';

    public function __construct(
        private readonly int $ttl = 3600,
    ) {
    }

    public function exists(Uuid $id): bool
    {
        return (bool) Redis::exists($this->key($id));
    }

    public function read(Uuid $id): string|false
    {
        $value = Redis::get($this->key($id));
        return is_string($value) && $value !== '' ? $value : false;
    }

    public function write(Uuid $id, string $data): bool
    {
        return (bool) Redis::setex($this->key($id), max(1, $this->ttl), $data);
    }

    public function destroy(Uuid $id): bool
    {
        return (bool) Redis::del($this->key($id));
    }

    public function gc(): array
    {
        // Redis 键随 TTL 自动过期，无过期项可收集
        return [];
    }

    private function key(Uuid $id): string
    {
        return self::KEY_PREFIX . $id->toRfc4122();
    }
}
