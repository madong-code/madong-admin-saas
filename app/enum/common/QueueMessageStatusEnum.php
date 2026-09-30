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

use core\foundation\interface\IEnum;

/**
 * 队列消息状态枚举
 */
enum QueueMessageStatusEnum: string implements IEnum
{
    case PENDING    = 'pending';    // 等待执行
    case PROCESSING = 'processing'; // 执行中
    case SUCCESS    = 'success';    // 执行成功
    case FAILED     = 'failed';     // 执行失败
    case DEAD       = 'dead';       // 死信（超过重试上限）

    public function label(): string
    {
        return match ($this) {
            self::PENDING    => '等待执行',
            self::PROCESSING => '执行中',
            self::SUCCESS    => '执行成功',
            self::FAILED     => '执行失败',
            self::DEAD       => '死信',
        };
    }

    /**
     * 获取所有状态值列表
     *
     * @return string[]
     */
    public static function values(): array
    {
        return array_map(fn(self $case) => $case->value, self::cases());
    }
}
