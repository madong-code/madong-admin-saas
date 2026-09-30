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
use core\infrastructure\scheduler\event\EvalTask;
use core\infrastructure\scheduler\event\SchedulingTask;
use core\infrastructure\scheduler\event\ShellTask;
use core\infrastructure\scheduler\event\UrlTask;

return [
    'enable'      => env('APP_TASK_ENABLED', false),// 是否启用定时器  修改此参数后，需要重启
    'debug'       => config('app.debug'),
    'write_log'   => false,
    'listen'      => '127.0.0.1:' . '2001',// 注意此端口用于任务通讯，一个项目一个端口，请勿占用
    'task_handle' => [
        //任务操作类
        \app\enum\system\TaskScheduleType::TASK_URL->value      => UrlTask::class,
        \app\enum\system\TaskScheduleType::TASK_EVAL->value     => EvalTask::class,
        \app\enum\system\TaskScheduleType::TASK_SHELL->value    => ShellTask::class,
        \app\enum\system\TaskScheduleType::TASK_SCHEDULE->value => SchedulingTask::class,
    ],
];
