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

enum MessageType: int
{
    case SYSTEM_MESSAGE = 1;//系统消息
    case ANNOUNCEMENT = 2;//公告
    case OPERATION_LOG = 3;//操作日志
    case WARNING = 4;//警告
    case USER_PRIVATE_MESSAGE = 5;//用户私信
    case WORKFLOW_MESSAGE = 6;//工作流消息

    // 定义一个方法以获取状态的描述
    public function label(): string
    {
        return match ($this) {
            self::SYSTEM_MESSAGE => '系统',
            self::ANNOUNCEMENT => '公告',
            self::OPERATION_LOG => '操作日志',
            self::WARNING => '警告',
            self::USER_PRIVATE_MESSAGE => '私信',
            self::WORKFLOW_MESSAGE => '工作流',
        };
    }

    // 设置标签颜色
    public function color(): string
    {
        return match ($this) {
            self::SYSTEM_MESSAGE => 'blue',        // 系统消息
            self::ANNOUNCEMENT => 'green',         // 公告
            self::OPERATION_LOG => 'grey',         // 操作日志
            self::WARNING => 'orange',             // 警告
            self::USER_PRIVATE_MESSAGE => 'purple', // 私信
            self::WORKFLOW_MESSAGE => 'cyan',      // 工作流
        };
    }
}
