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
namespace app\enum\member;



use core\foundation\interface\IEnum;

/**
 * 菜单类型枚举
 */
enum MenuType: int implements IEnum
{

    /**
     * 普通菜单
     */
    case NORMAL = 1;

    /**
     * 开通菜单
     */
    case OPEN = 2;

    /**
     * 获取枚举文本
     */
    public function label(): string
    {
        return match ($this) {
            self::NORMAL => '普通菜单',
            self::OPEN => '开通菜单',
        };
    }
}