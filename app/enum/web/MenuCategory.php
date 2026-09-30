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
namespace app\enum\web;

use core\foundation\interface\IEnum;

/**
 * 菜单分类枚举
 */
enum MenuCategory: int implements IEnum
{
    case NAV = 1;    // 导航菜单
    case MEMBER = 2; // 会员菜单

    /**
     * 获取文本
     */
    public function text(): string
    {
        return match ($this) {
            self::NAV => '导航菜单',
            self::MEMBER => '会员菜单',
        };
    }

    /**
     * 获取标签
     */
    public function label(): string
    {
        return $this->text();
    }
}
