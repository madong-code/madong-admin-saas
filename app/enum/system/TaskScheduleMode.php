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

enum TaskScheduleMode: int
{
    case CYCLE = 1;   // 循环模式
    case ONCE = 0;    // 单次模式

    public function label(): string
    {
        return match ($this) {
            self::CYCLE => '循环',
            self::ONCE => '单次',
        };
    }

    // 设置标签颜色
    public function color(): string
    {
        return match ($this) {
            self::CYCLE => '#2196F3',
            self::ONCE => '#4CAF50',
        };
    }

}
