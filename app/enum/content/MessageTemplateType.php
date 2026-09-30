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
 * 消息模板类型枚举
 */
enum MessageTemplateType: string implements IEnum
{
    case SYSTEM  = 'system';
    case SMS     = 'sms';
    case EMAIL   = 'email';
    case WEBHOOK = 'webhook';

    public function label(): string
    {
        return match ($this) {
            self::SYSTEM  => '系统内',
            self::SMS     => '短信',
            self::EMAIL   => '邮件',
            self::WEBHOOK => 'Webhook',
        };
    }
}
