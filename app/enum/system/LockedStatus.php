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

enum LockedStatus: int implements IEnum
{
    case LOCKED = 1;   // 锁定状态
    case UNLOCKED = 0; // 未锁定状态

    /**
     * 获取人类可读的标签
     */
    public function label(): string
    {
        return match ($this) {
            self::LOCKED => '是',    // 锁定状态显示"是"
            self::UNLOCKED => '否',  // 未锁定状态显示"否"
        };
    }

    /**
     * 获取对应的颜色值
     */
    public function color(): string
    {
        return match ($this) {
            self::LOCKED => '#FF5252',  // 锁定状态显示红色
            self::UNLOCKED => '#4CAF50',// 未锁定状态显示绿色
        };
    }
}
