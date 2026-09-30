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

enum YesNoStatus: int
{
    case YES = 1;
    case NO = 0;

    /**
     * 获取人类可读的标签
     */
    public function label(): string
    {
         return match ($this) {
            self::YES => '是',
            self::NO => '否',
        };
    }

    /**
     * 获取对应的颜色值
     */
    public function color(): string
    {
        return match ($this) {
            self::YES => '#4CAF50',  // 绿色
            self::NO => '#FF5252',  // 红色
        };
    }
}
