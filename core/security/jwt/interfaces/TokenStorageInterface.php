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
namespace core\security\jwt\interfaces;

/**
 * Token 存储接口
 *
 * 可实现此接口以支持不同的存储后端（Redis、MySQL、Memcached 等）
 */
interface TokenStorageInterface
{
    /**
     * 保存 Token 信息
     *
     * @param string $jti Token 唯一标识
     * @param array $data Token 数据
     * @param int $ttl 有效期（秒）
     * @param bool $addToUserIndex 是否加入用户索引（refresh_token 应设为 false，避免 getSessions 出现重复记录）
     * @return bool
     */
    public function save(string $jti, array $data, int $ttl, bool $addToUserIndex = true): bool;

    /**
     * 获取 Token 信息
     *
     * @param string $jti
     * @return array|null
     */
    public function get(string $jti): ?array;

    /**
     * 删除 Token 信息
     *
     * @param string $jti
     * @return bool
     */
    public function delete(string $jti): bool;

    /**
     * 验证 Token 是否存在
     *
     * @param string $jti
     * @return bool
     */
    public function exists(string $jti): bool;

    /**
     * 根据用户删除所有 Token
     *
     * @param string $id 用户ID（雪花ID）
     * @param string|null $clientType
     * @param string|null $exceptJti 保留的 JTI
     * @param int|string|null $tenantId 租户过滤（null=不过滤，单体模式/超管）
     * @return int 删除数量
     */
    public function deleteByUser(string $id, ?string $clientType = null, ?string $exceptJti = null, int|string|null $tenantId = null): int;

    /**
     * 删除最早的 Token
     *
     * @param string $id 用户ID（雪花ID）
     * @param int|string|null $tenantId 租户过滤（null=不过滤）
     * @return bool
     */
    public function deleteOldest(string $id, int|string|null $tenantId = null): bool;

    /**
     * 获取用户的所有 Token 数量
     *
     * @param string $id 用户ID（雪花ID）
     * @param string|null $clientType
     * @param int|string|null $tenantId 租户过滤（null=不过滤）
     * @return int
     */
    public function countByUser(string $id, ?string $clientType = null, int|string|null $tenantId = null): int;

    /**
     * 获取用户的所有 Token
     *
     * @param string $id 用户ID（雪花ID）
     * @return array
     */
    public function getByUser(string $id): array;

    /**
     * 获取用户的所有 Token 信息（包含索引信息）
     *
     * @param string $id 用户ID（雪花ID）
     * @param string|null $clientType 客户端类型
     * @param int|string|null $tenantId 租户过滤（null=不过滤）
     * @return array
     */
    public function getUserTokens(string $id, ?string $clientType = null, int|string|null $tenantId = null): array;

    /**
     * 从用户索引中移除指定 JTI
     *
     * @param string $id 用户ID（雪花ID）
     * @param string $jti Token JTI
     * @return bool
     */
    public function removeFromUserIndex(string $id, string $jti): bool;
}
