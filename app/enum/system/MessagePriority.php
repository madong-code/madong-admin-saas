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

/**
 * 消息优先级枚举
 */
enum MessagePriority: int
{
    case LOW      = 1;  // 低优先级
    case NORMAL   = 3;  // 普通（默认）
    case HIGH     = 5;  // 高优先级
    case URGENT   = 8;  // 紧急
    case CRITICAL = 10; // 关键

    /**
     * 获取优先级标签
     */
    public function label(): string
    {
        return match ($this) {
            self::LOW      => '低',
            self::NORMAL   => '普通',
            self::HIGH     => '高',
            self::URGENT   => '紧急',
            self::CRITICAL => '关键',
        };
    }

    /**
     * 获取优先级颜色（前端可用）
     */
    public function color(): string
    {
        return match ($this) {
            self::LOW      => 'info',
            self::NORMAL   => 'default',
            self::HIGH     => 'warning',
            self::URGENT   => 'danger',
            self::CRITICAL => 'danger',
        };
    }
}
