<?php
/**
 *+------------------
 * madong - 消息分类/模块种子数据
 *+------------------
 * Copyright (c) https://gitee.com/motion-code  All rights reserved.
 *+------------------
 * Author: Mr. April (405784684@qq.com)
 *+------------------
 * Official Website: https://madong.tech
 *
 * sys_message_category 采用 pid 模式（同菜单）。
 * - pid=0 为顶级分类
 * - definitions 数组为消息定义（子级）
 *
 * 安装时由 MessageDataTrait 导入，运行时以 DB 为主数据源。
 * 插件可在 plugin/{name}/resource/data/message/category.php 中以相同格式定义。
 */

return [
    [
        'key'         => 'daily',
        'name'        => '日常通知',
        'icon'        => 'bell',
        'description' => '系统日常通知消息',
        'sort'        => 10,
        'definitions' => [
            [
                'key'         => 'daily_notice',
                'name'        => '日常提醒',
                'description' => '日常提醒类消息',
                'default_on'  => true,
                'nav_type'    => 'none',
                'nav_value'   => '',
                'sort'        => 10,
            ],
            [
                'key'         => 'daily_news',
                'name'        => '新闻动态',
                'description' => '新闻动态类消息',
                'default_on'  => true,
                'nav_type'    => 'none',
                'nav_value'   => '',
                'sort'        => 20,
            ],
        ],
    ],
    [
        'key'         => 'attendance',
        'name'        => '考勤打卡',
        'icon'        => 'clock',
        'description' => '考勤相关通知',
        'sort'        => 20,
        'definitions' => [
            [
                'key'         => 'attendance_remind',
                'name'        => '打卡提醒',
                'description' => '打卡提醒通知',
                'default_on'  => true,
                'nav_type'    => 'router',
                'nav_value'   => '/content/attendance',
                'sort'        => 10,
            ],
            [
                'key'         => 'attendance_leave',
                'name'        => '请假审批',
                'description' => '请假审批通知',
                'default_on'  => true,
                'nav_type'    => 'router',
                'nav_value'   => '/content/attendance/leave',
                'sort'        => 20,
            ],
        ],
    ],
    [
        'key'         => 'approval',
        'name'        => 'OA审批',
        'icon'        => 'file-text',
        'description' => '审批流程通知',
        'sort'        => 30,
        'definitions' => [
            [
                'key'         => 'approval_wait',
                'name'        => '待审批',
                'description' => '待审批事项通知',
                'default_on'  => true,
                'nav_type'    => 'router',
                'nav_value'   => '/content/approval/wait',
                'sort'        => 10,
            ],
            [
                'key'         => 'approval_result',
                'name'        => '审批结果',
                'description' => '审批结果通知',
                'default_on'  => true,
                'nav_type'    => 'router',
                'nav_value'   => '/content/approval/result',
                'sort'        => 20,
            ],
        ],
    ],
    [
        'key'         => 'system',
        'name'        => '系统通知',
        'icon'        => 'alert-triangle',
        'description' => '系统级别通知',
        'sort'        => 40,
        'definitions' => [
            [
                'key'         => 'system_upgrade',
                'name'        => '系统升级',
                'description' => '系统升级通知',
                'default_on'  => true,
                'nav_type'    => 'none',
                'nav_value'   => '',
                'sort'        => 10,
            ],
            [
                'key'         => 'system_maintenance',
                'name'        => '维护通知',
                'description' => '系统维护通知',
                'default_on'  => true,
                'nav_type'    => 'none',
                'nav_value'   => '',
                'sort'        => 20,
            ],
        ],
    ],
];
