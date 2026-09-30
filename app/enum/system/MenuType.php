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
 * 菜单类型
 *
 * @author Mr.April
 * @since  1.0
 */
enum MenuType: int
{
    case CATALOG = 1;
    case MENU = 2;
    case BUTTON = 3;
    case API = 4;

    /**
     * 转换为可读类型名称
     *
     * @return string
     */
    public function label(): string
    {
        return match ($this) {
            self::CATALOG => '目录',
            self::MENU => '菜单',
            self::BUTTON => '按钮',
            self::API => '接口',
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
            self::CATALOG => '#409EFF',
            self::MENU => '#67C23A',
            self::BUTTON => '#E6A23C',
            self::API => '#909399',
        };
    }

}