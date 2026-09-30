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

namespace app\enum\content;

use core\foundation\interface\IEnum;

/**
 * 消息推送规则枚举
 */
enum MessageTemplatePushRule: int implements IEnum
{
    case IMMEDIATE = 0;
    case DELAYED   = 1;

    public function label(): string
    {
        return match ($this) {
            self::IMMEDIATE => '即时',
            self::DELAYED   => '延迟',
        };
    }
}
