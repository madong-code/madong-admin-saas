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

use core\foundation\interface\IEnum;

enum MenuOpenType:int implements IEnum
{

    case NONE = 0;          // 无
    case COMPONENT = 1;     // 组件
    case INTERNAL_LINK = 2; // 内链
    case EXTERNAL_LINK = 3; // 外链


    public function label(): string
    {
        return match($this) {
            self::NONE => '无',
            self::COMPONENT => '组件',
            self::INTERNAL_LINK => '内链',
            self::EXTERNAL_LINK => '外链',
        };
    }
}
