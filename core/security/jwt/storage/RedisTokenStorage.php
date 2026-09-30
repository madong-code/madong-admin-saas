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

use core\security\jwt\interfaces\TokenStorageInterface;
use support\Redis;

/**
 * Redis Token 存储
 */
class RedisTokenStorage implements TokenStorageInterface
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
     * @var string 用户索引 Key 前缀
     */
    protected const USER_INDEX_PREFIX = 'user:';

    public function __construct(array $config = [])
    {
        $this->config = $config;
        $this->prefix = $config['storage']['redis_prefix'] ?? 'jwt2:';
    }

    /**
     * 保存 Token 信息
     *
     * @param bool $addToUserIndex 是否加入用户索引（refresh_token 应设为 false，避免会话列表重复）
     */
    public function save(string $jti, array $data, int $ttl, bool $addToUserIndex = true): bool
    {
        $key = $this->prefix . $jti;
        $id = $data['id'] ?? '';
        $clientType = $data['client_type'] ?? '';

        // 保存 Token 数据
        Redis::setex($key, $ttl, json_encode($data));

        // 添加用户索引（保存 refresh_jti 以便踢人时使用）
        // refresh_token 不应加入用户索引，否则每个登录会显示两条会话记录
        if ($addToUserIndex && !empty($id)) {
            $userKey  = $this->prefix . self::USER_INDEX_PREFIX . $id;
            $tenantId = $data['extra']['current_tenant']['id'] ?? null;
            Redis::hset($userKey, $jti, json_encode([
                'client_type' => $clientType,
                'created_at'  => $data['created_at'] ?? time(),
                'refresh_jti' => $data['refresh_jti'] ?? '',
                'tenant_id'   => $tenantId,
            ]));
            // 设置用户索引过期时间（比 refresh_token 长一点）
            Redis::expire($userKey, $this->config['ttl']['refresh'] ?? 604800);
        }

        return true;
    }

    /**
     * 获取 Token 信息
     */
    public function get(string $jti): ?array
    {
        $key = $this->prefix . $jti;
        $data = Redis::get($key);

        if ($data === null || $data === false) {
            return null;
        }

        return json_decode((string) $data, true);
    }

    /**
     * 删除 Token 信息
     */
    public function delete(string $jti): bool
    {
        $key = $this->prefix . $jti;

        // 获取数据以便清理用户索引
        $data = $this->get($jti);

        Redis::del($key);

        // 清理用户索引
        if ($data && !empty($data['id'])) {
            $userKey = $this->prefix . self::USER_INDEX_PREFIX . $data['id'];
            Redis::hdel($userKey, $jti);
        }

        return true;
    }

    /**
     * 验证 Token 是否存在
     */
    public function exists(string $jti): bool
    {
        $key = $this->prefix . $jti;
        return (bool) Redis::exists($key);
    }

    /**
     * 根据用户删除所有 Token
     */
    public function deleteByUser(string $id, ?string $clientType = null, ?string $exceptJti = null, int|string|null $tenantId = null): int
    {
        $userKey = $this->prefix . self::USER_INDEX_PREFIX . $id;
        $jtis = Redis::hgetall($userKey);

        if (empty($jtis)) {
            return 0;
        }

        $count = 0;
        foreach ($jtis as $jtiKey => $info) {
            // 跳过保留的 JTI
            if ($exceptJti !== null && $jtiKey === $exceptJti) {
                continue;
            }

            $infoData = json_decode((string) $info, true) ?: [];

            // 如果指定了客户端类型，只删除匹配的
            if ($clientType !== null && ($infoData['client_type'] ?? '') !== $clientType) {
                continue;
            }

            // 多租户过滤
            if ($tenantId !== null && $this->isTenantMismatch($infoData, $tenantId)) {
                continue;
            }

            // 删除 Token
            Redis::del($this->prefix . $jtiKey);
            Redis::hdel($userKey, $jtiKey);
            $count++;
        }

        return $count;
    }
    
    /**
     * 获取用户的所有 Token 信息
     */
    public function getUserTokens(string $id, ?string $clientType = null, int|string|null $tenantId = null): array
    {
        $userKey = $this->prefix . self::USER_INDEX_PREFIX . $id;
        $jtis = Redis::hgetall($userKey);

        $tokens = [];
        foreach ($jtis as $jtiKey => $info) {
            $infoData = json_decode((string) $info, true) ?: [];

            // 如果指定了客户端类型，只返回匹配的
            if ($clientType !== null && ($infoData['client_type'] ?? '') !== $clientType) {
                continue;
            }

            // 多租户过滤
            if ($tenantId !== null && $this->isTenantMismatch($infoData, $tenantId)) {
                continue;
            }

            $tokens[$jtiKey] = $infoData;
        }

        return $tokens;
    }

    /**
     * 删除最早的 Token
     */
    public function deleteOldest(string $id, int|string|null $tenantId = null): bool
    {
        $userKey = $this->prefix . self::USER_INDEX_PREFIX . $id;
        $jtis = Redis::hgetall($userKey);

        if (empty($jtis)) {
            return false;
        }

        // 找出最早的
        $oldestJti = null;
        $oldestTime = PHP_INT_MAX;

        foreach ($jtis as $jtiKey => $info) {
            $infoData = json_decode((string) $info, true) ?: [];

            // 多租户过滤
            if ($tenantId !== null && $this->isTenantMismatch($infoData, $tenantId)) {
                continue;
            }

            $createdAt = $infoData['created_at'] ?? 0;
            if ($createdAt < $oldestTime) {
                $oldestTime = $createdAt;
                $oldestJti = $jtiKey;
            }
        }

        if ($oldestJti !== null) {
            return $this->delete($oldestJti);
        }

        return false;
    }

    /**
     * 获取用户的所有 Token 数量
     */
    public function countByUser(string $id, ?string $clientType = null, int|string|null $tenantId = null): int
    {
        $userKey = $this->prefix . self::USER_INDEX_PREFIX . $id;
        $jtis = Redis::hgetall($userKey);

        if (empty($jtis)) {
            return 0;
        }

        $count = 0;
        foreach ($jtis as $jtiKey => $info) {
            $infoData = json_decode((string) $info, true) ?: [];

            // 如果指定了客户端类型，只统计匹配的
            if ($clientType !== null && ($infoData['client_type'] ?? '') !== $clientType) {
                continue;
            }

            // 多租户过滤
            if ($tenantId !== null && $this->isTenantMismatch($infoData, $tenantId)) {
                continue;
            }

            $count++;
        }

        return $count;
    }

    /**
     * 获取用户的所有 Token
     */
    public function getByUser(string $id): array
    {
        $userKey = $this->prefix . self::USER_INDEX_PREFIX . $id;
        $jtis = Redis::hgetall($userKey);

        $tokens = [];
        foreach ($jtis as $jtiKey => $info) {
            $tokenData = $this->get($jtiKey);
            if ($tokenData !== null) {
                $tokens[] = $tokenData;
            } else {
                // 清理失效的索引
                Redis::hdel($userKey, $jtiKey);
            }
        }

        return $tokens;
    }

    /**
     * 判断索引数据与目标租户是否不匹配（多租户过滤用）
     *
     * 规则：
     *   - 索引中无 tenant_id（旧 Token）→ 不跳过（向后兼容，不过滤）
     *   - 索引中 tenant_id 与目标一致 → 不跳过
     *   - 索引中 tenant_id 与目标不一致 → 跳过
     *
     * @param array $indexData 用户索引中的 hash value 数据
     * @param int|string $tenantId 目标租户 ID
     * @return bool true=不匹配应跳过
     */
    private function isTenantMismatch(array $indexData, int|string $tenantId): bool
    {
        $stored = $indexData['tenant_id'] ?? null;
        // tenant_id 未存储（旧 Token）：不跳过，向后兼容
        if ($stored === null || $stored === '') {
            return false;
        }
        // 存储了 tenant_id 但不匹配：跳过
        return (string) $stored !== (string) $tenantId;
    }

    /**
     * 从用户索引中移除指定 JTI
     *
     * @param string $id 用户ID
     * @param string $jti Token JTI
     * @return bool
     */
    public function removeFromUserIndex(string $id, string $jti): bool
    {
        $userKey = $this->prefix . self::USER_INDEX_PREFIX . $id;
        return (bool) Redis::hdel($userKey, $jti);
    }
}
