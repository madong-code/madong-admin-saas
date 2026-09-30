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
namespace app\api\event\member;

use core\foundation\base\BaseEvent;

/**
 * 会员用户信息获取事件
 *
 * 当获取用户信息时触发，允许监听器动态添加权限、自定义字段等
 */
class MemberInfoFetchedEvent extends BaseEvent
{
    /**
     * 会员ID（支持字符串型的 Snowflake ID）
     */
    public int|string $memberId;

    /**
     * 用户数据（允许监听器修改）
     */
    public array $userData = [];

    /**
     * 权限数组（允许监听器追加）
     */
    public array $permissions = [];

    /**
     * 额外数据（允许插件等追加自定义字段）
     */
    public array $extra = [];

    /**
     * 构造函数
     */
    public function __construct(int|string $memberId, array $userData = [], array $permissions = [], array $extra = [])
    {
        $this->memberId = $memberId;
        $this->userData = $userData;
        $this->permissions = $permissions;
        $this->extra = $extra;
    }

    /**
     * 获取用户数据
     */
    public function getUserData(): array
    {
        return $this->userData;
    }

    /**
     * 获取权限数组
     */
    public function getPermissions(): array
    {
        return $this->permissions;
    }

    /**
     * 获取额外数据
     */
    public function getExtra(): array
    {
        return $this->extra;
    }

    public function getEventName(): string
    {
        return 'member.info.fetched';
    }

    /**
     * 添加权限
     */
    public function addPermission(string $permission): void
    {
        if (!in_array($permission, $this->permissions)) {
            $this->permissions[] = $permission;
        }
    }

    /**
     * 批量添加权限
     */
    public function addPermissions(array $permissions): void
    {
        foreach ($permissions as $permission) {
            $this->addPermission($permission);
        }
    }

    /**
     * 添加额外字段
     */
    public function addExtra(string $key, mixed $value): void
    {
        $this->extra[$key] = $value;
    }

    /**
     * 添加用户数据字段
     */
    public function addUserData(string $key, mixed $value): void
    {
        $this->userData[$key] = $value;
    }

    /**
     * 检查是否有某个权限
     */
    public function hasPermission(string $permission): bool
    {
        return in_array($permission, $this->permissions);
    }
}
