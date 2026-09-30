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
 * Official Website: https://madong.tech
 */

namespace app\enum\system;

/**
 * 租户隔离模式
 *
 * 系统支持三种运行模式：
 * - SINGLE: 非租户模式，关闭多租户功能，直接使用公共库
 * - FIELD: 字段隔离模式，通过 tenant_id 字段区分租户
 * - DB: 库隔离模式，每个租户独立数据库
 *
 * @author Mr.April
 * @since  1.0
 */
enum TenantMode: string
{
    case SINGLE = 'single';
    case FIELD = 'field';
    case DB = 'database';

    /**
     * 获取人类可读的标签
     */
    public function label(): string
    {
        return match ($this) {
            self::SINGLE => '非租户模式',
            self::FIELD => '字段隔离',
            self::DB => '库隔离',
        };
    }

    /**
     * 获取颜色标识
     */
    public function color(): string
    {
        return match ($this) {
            self::SINGLE => 'default',
            self::FIELD => 'blue',
            self::DB => 'purple',
        };
    }

    /**
     * 判断是否启用多租户（SINGLE模式不启用）
     */
    public function isMultiTenant(): bool
    {
        return $this !== self::SINGLE;
    }

    /**
     * 判断是否为字段隔离模式
     */
    public function isField(): bool
    {
        return $this === self::FIELD;
    }

    /**
     * 判断是否为库隔离模式
     */
    public function isDb(): bool
    {
        return $this === self::DB;
    }

    /**
     * 从配置值解析枚举
     *
     * @param string $value
     * @return self
     */
    public static function fromConfig(string $value): self
    {
        return match ($value) {
            'single' => self::SINGLE,
            'field' => self::FIELD,
            'database' => self::DB,
            default => self::SINGLE,
        };
    }
}
