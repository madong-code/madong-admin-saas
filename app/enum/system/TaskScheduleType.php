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

enum TaskScheduleType: int
{
    case TASK_URL = 1;
    case TASK_EVAL = 2;

    case TASK_SHELL = 3;

    CASE TASK_SCHEDULE= 4;

    public function label(): string
    {
        return match ($this) {
            self::TASK_URL => 'HTTP 请求（GET/POST）',
            self::TASK_EVAL => 'PHP 代码执行',
            self::TASK_SHELL => '系统命令执行',
            self::TASK_SCHEDULE=>'任务调度'
        };
    }

}
