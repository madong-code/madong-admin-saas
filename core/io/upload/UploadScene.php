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
namespace core\io\upload;

/**
 * 上传场景值对象
 *
 * 封装 group_code，用于区分不同配置作用域：
 * - platform → 平台级配置（group_code=platform）
 * - admin    → 租户/单体配置（group_code=default）
 * - api      → API 端默认配置（group_code=default）
 */
class UploadScene
{
    /**
     * 平台级场景
     */
    public const GROUP_PLATFORM = 'platform';

    /**
     * 管理端场景
     */
    public const GROUP_DEFAULT = 'default';

    private string $groupCode;

    private function __construct(string $groupCode)
    {
        $this->groupCode = $groupCode;
    }

    /**
     * 平台场景
     */
    public static function platform(): self
    {
        return new self(self::GROUP_PLATFORM);
    }

    /**
     * 管理端场景（默认值）
     */
    public static function admin(): self
    {
        return new self(self::GROUP_DEFAULT);
    }

    /**
     * API 端默认场景（group_code=default）
     */
    public static function api(): self
    {
        return new self(self::GROUP_DEFAULT);
    }

    /**
     * 根据名称创建场景
     */
    public static function fromName(string $name): self
    {
        return match ($name) {
            self::GROUP_PLATFORM => self::platform(),
            default => self::admin(),
        };
    }

    /**
     * 获取 group_code
     */
    public function getGroupCode(): string
    {
        return $this->groupCode;
    }

    /**
     * 是否为平台场景
     */
    public function isPlatform(): bool
    {
        return $this->groupCode === self::GROUP_PLATFORM;
    }

    /**
     * 是否为管理端场景
     */
    public function isAdmin(): bool
    {
        return $this->groupCode === self::GROUP_DEFAULT;
    }
}
