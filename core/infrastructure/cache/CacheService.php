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
namespace core\infrastructure\cache;

use Symfony\Component\Cache\Adapter\AdapterInterface;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Adapter\RedisAdapter;
use support\Context;

/**
 * 缓存服务
 *
 * 协程安全说明：
 *   PHP-DI 会把本类作为「共享实例」注入到多个服务中（同一进程内所有请求复用同一对象），
 *   而 Swow 会 hook ext-redis，单个 phpredis 连接被多个协程同时读写会出现
 *   "read error on connection"，因此缓存连接必须按协程隔离。
 *
 *   本类构造函数只读取配置、不做任何 IO（否则 DI 解析期间发生阻塞会让出协程，
 *   触发 PHP-DI 基于实例状态的循环依赖误判）；
 *   真正的连接延迟到首次读写时建立，并按协程存入 support\Context，实现按协程隔离。
 *   非协程场景下 Context 退化为进程内全局，与改造前行为一致。
 *   类名 / 命名空间 / 文件路径 / 公开方法签名保持不变，调用方无需改动。
 */
class CacheService
{
    /** 协程上下文键：缓存当前协程独占的 [AdapterInterface, ?\Redis] */
    private const CONTEXT_KEY = 'cache_service.local';

    private string $namespace = ''; // 命名空间
    private string $prefix = ''; // 默认前缀
    private string $adapter = 'file'; // 缓存适配器类型
    private array $options = []; // 连接参数

    public function __construct(array $options = [])
    {
        // 获取框架的 Redis 配置
        $this->options   = array_merge([
            'host'     => '127.0.0.1',
            'password' => null,
            'port'     => 6379,
            'database' => 0,
        ], config('cache.custom.default', []), $options);
        $this->adapter   = config('cache.custom.type', 'file');
        $this->prefix    = config('cache.custom.prefix', '');
        $this->namespace = config('cache.custom.namespace', '');
    }

    /**
     * 获取当前协程独占的缓存适配器（延迟初始化，首次使用时才建立连接）
     *
     * @throws \RedisException
     */
    private function adapter(): AdapterInterface
    {
        $local = Context::get(self::CONTEXT_KEY);
        if (is_array($local)) {
            return $local[0];
        }

        $redis = null;
        if ($this->adapter === 'redis') {
            $redis = new \Redis();
            $redis->connect($this->options['host'], (int) ($this->options['port'] ?? 6379)); // 默认端口6379
            if (!empty($this->options['password'])) {
                $redis->auth($this->options['password']);
            }
            if (!empty($this->options['database'])) {
                $redis->select((int) $this->options['database']);
            }
            $cache = new RedisAdapter($redis, $this->namespace);
        } else {
            //默认file模式
            $cache = new FilesystemAdapter($this->prefix);
        }

        Context::set(self::CONTEXT_KEY, [$cache, $redis]);

        return $cache;
    }

    /**
     * 获取当前协程独占的 Redis 客户端（file 模式返回 null）
     *
     * @throws \RedisException
     */
    private function client(): ?\Redis
    {
        $this->adapter(); // 确保已初始化
        $local = Context::get(self::CONTEXT_KEY);

        return is_array($local) ? ($local[1] ?? null) : null;
    }

    // 读取缓存
    public function get(string $key, $default = null)
    {
        $item = $this->adapter()->getItem($this->prefix . $key);
        return $item->isHit() ? $item->get() : $default;
    }

    // 写入缓存
    public function set(string $key, $value, int $ttl = 3600): void
    {
        $item = $this->adapter()->getItem($this->prefix . $key);
        $item->set($value);
        $item->expiresAfter($ttl);
        $this->adapter()->save($item);
    }

    // 读取缓存没有则回调查询
    public function remember(string $key, callable $callback, int $ttl = 3600)
    {
        $value = $this->get($key);
        if ($value === null) {
            $value = $callback();
            $this->set($key, $value, $ttl);
        }
        return $value;
    }

    // 删除
    public function delete(string $key)
    {
        $this->adapter()->deleteItem($this->prefix . $key);
    }

    // 清空缓存
    public function clear(string $prefix = ''): void
    {
        $this->clearByPrefix($prefix);
    }

    private function clearByPrefix(string $prefix): void
    {
        $redis = $this->client();
        if ($this->adapter === 'redis' && $redis) {
            if (!empty($this->namespace)) {
                $prefix = $this->namespace . ':' . $prefix;
            }
            $keys = $redis->keys($prefix . '*'); // 查找所有匹配的键
            if (!empty($keys)) {
                $redis->del($keys); // 删除匹配的键
            }
        } else {
            $this->adapter()->clearPrefix($prefix); // 对于文件系统适配器，使用 clearPrefix
        }
    }

    // 设置锁
    public function setLock(string $lockKey, int $ttl = 30): bool
    {
        $redis = $this->client();
        if ($redis) {
            return $redis->set($lockKey, 'locked', ['nx', 'ex' => $ttl]);
        }
        return false; // Redis 未初始化，返回 false
    }

    // 释放锁
    public function releaseLock(string $lockKey): void
    {
        $redis = $this->client();
        if ($redis) {
            $redis->del($lockKey);
        }
    }

    // 检查锁
    public function checkLock(string $lockKey): bool
    {
        $redis = $this->client();
        return $redis && $redis->exists($lockKey) === 1;
    }

    // 检查键是否存在
    public function keyExists(string $key): bool
    {
        $item = $this->adapter()->getItem($this->prefix . $key);
        return $item->isHit();
    }
}