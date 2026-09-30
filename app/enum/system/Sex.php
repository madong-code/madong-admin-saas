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
 * 性别枚举
 * 对应数据字典 sex
 *
 * @author Mr.April
 * @since  1.0
 */
enum Sex: int implements IEnum
{

    case MALE = 1;       // 男
    case FEMALE = 2;     // 女
    case UNKNOWN = 0;    // 未知

    public function label(): string
    {
        return match ($this) {
            self::MALE => '男',
            self::FEMALE => '女',
            self::UNKNOWN => '未知',
        };
    }
}
