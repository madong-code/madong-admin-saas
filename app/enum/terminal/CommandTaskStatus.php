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
namespace app\enum\terminal;

use core\foundation\interface\IEnum;

/**
 * 命令任务枚举类
 *
 * @author Mr.April
 * @since  1.0
 */
enum CommandTaskStatus: int implements IEnum
{
    case Waiting = 0;
    case Connecting = 1;
    case Executing = 2;
    case Success = 3;
    case Failed = 4;
    case Unknown = 5;

    /**
     * 获取状态对应的 CSS 颜色类型
     * 用于前端显示样式
     */
    public function color(): string
    {
        return match ($this) {
            self::Waiting=>'purple',
            self::Connecting => 'orange',
            self::Executing => 'orange',
            self::Success => 'green',
            self::Failed => 'red',
            self::Unknown => 'blue',
        };
    }

    /**
     * 获取状态显示文本
     */
    public function label(): string
    {
        return match ($this) {
            self::Waiting => '任务已创建，等待执行',
            self::Connecting => '任务正在建立连接',
            self::Executing => '任务正在执行中',
            self::Success => '任务执行成功',
            self::Failed => '任务执行失败',
            self::Unknown => '任务状态未知',
        };
    }
}
