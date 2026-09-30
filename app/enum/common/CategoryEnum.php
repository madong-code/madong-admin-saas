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

namespace app\enum\common;

use core\foundation\interface\IEnum;

/**
 * 枚举分类映射枚举
 * 用于映射枚举目录分类名称
 */
enum CategoryEnum: string implements IEnum
{
    case COMMON = 'common';    // 通用枚举分类
    case SYSTEM = 'system';    // 系统枚举分类
    case DEV = 'dev';          // 开发枚举分类
    case MONITOR = 'monitor';  // 监控枚举分类
    case BUSINESS = 'business'; // 业务枚举分类

    /**
     * 获取分类描述
     */
    public function label(): string
    {
        return match ($this) {
            self::COMMON => '通用枚举',
            self::SYSTEM => '系统枚举',
            self::DEV => '开发枚举',
            self::MONITOR => '监控枚举',
            self::BUSINESS => '业务枚举',
        };
    }

    /**
     * 获取颜色标识
     */
    public function color(): string
    {
        return match ($this) {
            self::COMMON => 'blue',
            self::SYSTEM => 'green',
            self::DEV => 'orange',
            self::MONITOR => 'red',
            self::BUSINESS => 'purple',
        };
    }

    /**
     * 获取分类对应的目录路径
     */
    public function directory(): string
    {
        return match ($this) {
            self::COMMON => 'common',
            self::SYSTEM => 'system',
            self::DEV => 'dev',
            self::MONITOR => 'monitor',
            self::BUSINESS => 'business',
        };
    }

    /**
     * 获取所有分类的映射数组
     */
    public static function getCategoryMap(): array
    {
        $map = [];
        foreach (self::cases() as $case) {
            $map[$case->value] = [
                'code' => $case->value,
                'label' => $case->label(),
                'color' => $case->color(),
                'directory' => $case->directory(),
            ];
        }
        return $map;
    }

    /**
     * 根据目录名称获取分类枚举
     */
    public static function fromDirectory(string $directory): ?self
    {
        foreach (self::cases() as $case) {
            if ($case->directory() === $directory) {
                return $case;
            }
        }
        return null;
    }

    /**
     * 检查目录是否为有效的分类
     */
    public static function isValidDirectory(string $directory): bool
    {
        return self::fromDirectory($directory) !== null;
    }
}
