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

/**
 * 定时任务枚举类
 *
 * @author Mr.April
 * @since  1.0
 */
enum TaskScheduleCycle: int
{
 // 固定时间间隔
    case DAILY       = 1;   // 86400 秒（24小时）
    case HOURLY      = 2;   // 3600 秒
    case WEEKLY      = 3;   // 604800 秒（7天）
    case MONTHLY     = 4;   // 2592000 秒（30天近似值）
    case YEARLY      = 5;   // 31536000 秒（365天近似值）

    // 可变时间间隔（需配合数值参数使用）
    case N_HOURS     = 6;
    case N_MINUTES   = 7;
    case N_SECONDS   = 8;

    public function label(): string
    {
         return match($this) {
            self::DAILY       => '每天',
            self::HOURLY      => '每小时',
            self::WEEKLY      => '每周',
            self::MONTHLY     => '每月',
            self::YEARLY      => '每年',
            self::N_HOURS     => 'N小时',
            self::N_MINUTES   => 'N分钟',
            self::N_SECONDS   => 'N秒',
        };
    }
}
