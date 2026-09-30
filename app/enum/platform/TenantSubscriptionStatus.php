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

namespace app\enum\platform;

use core\foundation\interface\IEnum;

/**
 * 租户订阅状态枚举
 */
enum TenantSubscriptionStatus: string implements IEnum
{
    case ACTIVE = 'active';
    case TRIAL = 'trial';
    case EXPIRED = 'expired';
    case CANCELLED = 'cancelled';

    /**
     * 获取人类可读的标签
     */
    public function label(): string
    {
        return match ($this) {
            self::ACTIVE => '正常',
            self::TRIAL => '试用',
            self::EXPIRED => '已过期',
            self::CANCELLED => '已取消',
        };
    }

    /**
     * 获取对应的颜色值
     */
    public function color(): string
    {
        return match ($this) {
            self::ACTIVE => '#4CAF50',  // 绿色
            self::TRIAL => '#FF9800',   // 橙色
            self::EXPIRED => '#FF5252', // 红色
            self::CANCELLED => '#9E9E9E', // 灰色
        };
    }

    /**
     * 根据状态值获取标签
     */
    public static function getLabel(string $value): string
    {
        return self::tryFrom($value)?->label() ?? '未知';
    }

    /**
     * 根据状态值获取颜色
     */
    public static function getColor(string $value): string
    {
        return self::tryFrom($value)?->color() ?? '#9E9E9E';
    }

    /**
     * 获取所有状态选项（用于下拉框等）
     */
    public static function options(): array
    {
        return array_map(
            fn(self $status) => [
                'value' => $status->value,
                'label' => $status->label(),
                'color' => $status->color(),
            ],
            self::cases()
        );
    }
}
