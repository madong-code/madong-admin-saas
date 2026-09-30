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

/**
 * 角色类型枚举
 *
 * @author Mr.April
 * @since  1.0
 */
enum RoleType :int implements IEnum
{

    // 枚举成员定义
    case NORMAL = 1;
    case DATA = 2;

    public function label(): string
    {
        return match ($this){
            self::NORMAL=>'普通角色',
            self::DATA=>'数据角色',
        };
    }
}
