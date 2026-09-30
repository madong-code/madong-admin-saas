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

namespace app\enum\system;

/**
 * 菜单源类型
 *
 * @author Mr.April
 * @since  1.0
 */
enum MenuSource: string
{
    case SYSTEM = 'system';
    case AUTO_GENERATE = 'auto_generate';

    /**
     * 转换为可读类型名称
     * @return string
     */
    public function label(): string
    {
        return match($this) {
            self::SYSTEM => '系统菜单',
            self::AUTO_GENERATE => '自动生成',
        };
    }

    /**
     * 获取颜色标识
     *
     * @return string
     */
    public function color(): string
    {
        return match ($this) {
            self::SYSTEM => 'blue',
            self::AUTO_GENERATE => 'green',
        };
    }

    /**
     * 获取所有枚举值
     *
     * @return array
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * 获取所有枚举标签
     *
     * @return array
     */
    public static function labels(): array
    {
        $labels = [];
        foreach (self::cases() as $case) {
            $labels[$case->value] = $case->label();
        }
        return $labels;
    }
}