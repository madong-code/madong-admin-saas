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
return [
    // 基础配置
    'base'     => [
        'path'           => runtime_path('logs'), // 日志存储路径
        'channel'        => 'core',             // 日志通道名称
        'retention_days' => 7, // 默认保留7天
        'daily_rotation' => true, // 启用每日文件分割
    ],
    // 格式配置
    'format'   => [
        'date'   => 'Y-m-d H:i:s.u',      // 日期格式
        'output' => "[%datetime%] %channel%.%level_name%: %message% %context%\n",
    ],

    // 级别配置
    'levels'   => [
        'debug'     => config('app.debug', false),                // 是否启用debug日志
        'min_level' => 'debug',         // 最小记录级别
    ],

    // 处理器配置
    'handlers' => [
        'stream' => true,               // 启用文件处理器
        'syslog' => false,              // 是否启用syslog
    ],
];
