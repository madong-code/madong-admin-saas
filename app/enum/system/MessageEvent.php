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
 * 消息事件-枚举
 *
 * @author Mr.April
 * @since  1.0
 */
enum MessageEvent: string implements IEnum
{
    case DEFAULT = 'message';

    public function label(): string
    {
        return match ($this) {
            self::DEFAULT => '默认',
        };
    }
}
