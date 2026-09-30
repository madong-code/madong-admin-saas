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

enum NoticeType: string
{

    case ANNOUNCEMENT = 'announcement';
    case NOTICE = 'notice';



    //定义一个方法以获取状态的描述
    public function label(): string
    {
        return match ($this) {
            self::ANNOUNCEMENT => '公告',
            self::NOTICE => '通知',
        };
    }

    // 设置标签颜色
    public function color(): string
    {
        return match ($this) {
            self::ANNOUNCEMENT => '#2196F3',
            self::NOTICE => '#4CAF50',
        };
    }
}
