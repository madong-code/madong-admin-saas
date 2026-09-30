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
 * 黑名单存储接口
 *
 * 可实现此接口以支持不同的存储后端（Redis、MySQL、Memcached 等）
 */
interface BlacklistStorageInterface
{
    /**
     * 添加 Token 到黑名单
     *
     * @param string $jti Token 唯一标识
     * @param int $ttl 有效期（秒）
     * @return bool
     */
    public function add(string $jti, int $ttl): bool;

    /**
     * 检查 Token 是否在黑名单
     *
     * @param string $jti
     * @return bool
     */
    public function has(string $jti): bool;

    /**
     * 移除 Token 从黑名单
     *
     * @param string $jti
     * @return bool
     */
    public function remove(string $jti): bool;

    /**
     * 添加用户到黑名单
     *
     * @param string $id 用户ID（雪花ID）
     * @param int $ttl 有效期（秒）
     * @return bool
     */
    public function addUser(string $id, int $ttl): bool;

    /**
     * 检查用户是否在黑名单
     *
     * @param string $id 用户ID（雪花ID）
     * @return bool
     */
    public function hasUser(string $id): bool;

    /**
     * 移除用户从黑名单
     *
     * @param string $id 用户ID（雪花ID）
     * @return bool
     */
    public function removeUser(string $id): bool;

    /**
     * 获取黑名单剩余 TTL
     *
     * @param string $jti
     * @return int -1 表示不存在，-2 表示已过期
     */
    public function getTtl(string $jti): int;
}
