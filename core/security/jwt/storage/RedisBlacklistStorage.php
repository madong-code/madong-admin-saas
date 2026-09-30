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
namespace core\security\jwt\storage;

use core\security\jwt\interfaces\BlacklistStorageInterface;
use support\Redis;

/**
 * Redis 黑名单存储
 */
class RedisBlacklistStorage implements BlacklistStorageInterface
{
    /**
     * @var string 前缀
     */
    protected string $prefix;

    /**
     * @var array 配置
     */
    protected array $config;

    /**
     * @var string 用户黑名单 Key 后缀
     */
    protected const USER_BLACKLIST_SUFFIX = 'user:blacklist:';

    /**
     * @var string Token 黑名单 Key 后缀
     */
    protected const TOKEN_BLACKLIST_SUFFIX = 'blacklist:';

    public function __construct(array $config = [])
    {
        $this->config = $config;
        $this->prefix = $config['storage']['redis_prefix'] ?? 'jwt2:';
    }

    /**
     * 添加 Token 到黑名单
     */
    public function add(string $jti, int $ttl): bool
    {
        $key = $this->prefix . self::TOKEN_BLACKLIST_SUFFIX . $jti;
        Redis::setex($key, $ttl, '1');
        return true;
    }

    /**
     * 检查 Token 是否在黑名单
     */
    public function has(string $jti): bool
    {
        $key = $this->prefix . self::TOKEN_BLACKLIST_SUFFIX . $jti;
        return (bool) Redis::exists($key);
    }

    /**
     * 移除 Token 从黑名单
     */
    public function remove(string $jti): bool
    {
        $key = $this->prefix . self::TOKEN_BLACKLIST_SUFFIX . $jti;
        Redis::del($key);
        return true;
    }

    /**
     * 添加用户到黑名单
     */
    public function addUser(string $id, int $ttl): bool
    {
        $key = $this->prefix . self::USER_BLACKLIST_SUFFIX . $id;
        Redis::setex($key, $ttl, '1');
        return true;
    }

    /**
     * 检查用户是否在黑名单
     */
    public function hasUser(string $id): bool
    {
        $key = $this->prefix . self::USER_BLACKLIST_SUFFIX . $id;
        return (bool) Redis::exists($key);
    }

    /**
     * 移除用户从黑名单
     */
    public function removeUser(string $id): bool
    {
        $key = $this->prefix . self::USER_BLACKLIST_SUFFIX . $id;
        Redis::del($key);
        return true;
    }

    /**
     * 获取黑名单剩余 TTL
     */
    public function getTtl(string $jti): int
    {
        $key = $this->prefix . self::TOKEN_BLACKLIST_SUFFIX . $jti;
        return (int) Redis::ttl($key);
    }
}
