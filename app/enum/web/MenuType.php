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
 * 导航类型枚举
 */
enum MenuType: int implements IEnum
{
    case DIRECTORY = 1; // 目录

    case NAV = 2;       // 导航菜单
    case LINK = 3;      // 外部链接
    case PAGE = 4;      // 单页面

    /**
     * 获取文本
     */
    public function text(): string
    {
        return match ($this) {
            self::NAV => '导航菜单',
            self::LINK => '外部链接',
            self::PAGE => '单页面',
            self::DIRECTORY => '目录',
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
