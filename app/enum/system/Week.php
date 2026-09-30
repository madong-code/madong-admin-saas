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
 * Official Website: https://madong.tech
 */

namespace app\enum\system;

enum Week: int
{
    // 定义枚举项
    case Monday = 1;
    case Tuesday = 2;
    case Wednesday = 3;
    case Thursday = 4;
    case Friday = 5;
    case Saturday = 6;
    case Sunday = 0;

    // 获取中文标签
    public function label(): string
    {
        return match($this) {
            self::Monday => '周一',
            self::Tuesday => '周二',
            self::Wednesday => '周三',
            self::Thursday => '周四',
            self::Friday => '周五',
            self::Saturday => '周六',
            self::Sunday => '周日',
        };
    }
}
