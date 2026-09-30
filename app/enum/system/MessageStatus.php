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

enum MessageStatus: string
{

    case UNREAD = 'unread';
    case READ = 'read';

    //定义一个方法以获取状态的描述
    public function label(): string
    {
        return match ($this) {
            self::UNREAD => '未读',
            self::READ => '已读',
        };
    }

    // 设置标签颜色
    public function color(): string
    {
        return match ($this) {
            self::UNREAD => 'orange',
            self::READ => 'green',
        };
    }
}
