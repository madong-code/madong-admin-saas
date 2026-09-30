<?php
/**
 *+------------------
 * madong - 系统默认语言包
 *+------------------
 * Copyright (c) https://gitee.com/motion-code  All rights reserved.
 *+------------------
 * Author: Mr. April (405784684@qq.com)
 *+------------------
 * Official Website: https://madong.tech
 */

return [
    // 用户管理
    'user'         => [
        'title'                  => '用户管理',
        'not_exist'              => '用户不存在',
        'create_success'         => '用户创建成功',
        'create_fail'            => '用户创建失败',
        'update_success'         => '用户更新成功',
        'update_fail'            => '用户更新失败',
        'delete_success'         => '用户删除成功',
        'delete_fail'            => '用户删除失败',
        'enable_success'         => '用户启用成功',
        'enable_fail'            => '用户启用失败',
        'disable_success'        => '用户禁用成功',
        'disable_fail'           => '用户禁用失败',
        'lock_success'           => '用户锁定成功',
        'lock_fail'              => '用户锁定失败',
        'unlock_success'         => '用户解锁成功',
        'unlock_fail'            => '用户解锁失败',
        'password_reset_success' => '密码重置成功',
        'password_reset_fail'    => '密码重置失败',
        'username_exist'         => '用户名已存在',
        'email_exist'            => '邮箱已存在',
        'mobile_exist'           => '手机号已存在',
        'admin_not_editable'     => '超级管理员不可编辑',
        'admin_not_deletable'    => '超级管理员不可删除',
    ],

    // 角色管理
    'role'         => [
        'title'                    => '角色管理',
        'not_exist'                => '角色不存在',
        'create_success'           => '角色创建成功',
        'create_fail'              => '角色创建失败',
        'update_success'           => '角色更新成功',
        'update_fail'              => '角色更新失败',
        'delete_success'           => '角色删除成功',
        'delete_fail'              => '角色删除失败',
        'assign_success'           => '角色分配成功',
        'assign_fail'              => '角色分配失败',
        'admin_role_not_editable'  => '管理员角色不可编辑',
        'admin_role_not_deletable' => '管理员角色不可删除',
        'in_use'                   => '角色正在使用中，不可删除',
        'permission'               => [
            'assign_success' => '权限分配成功',
            'assign_fail'    => '权限分配失败',
            'update_success' => '权限更新成功',
            'update_fail'    => '权限更新失败',
        ],
    ],

    // 菜单管理
    'menu'         => [
        'title'          => '菜单管理',
        'not_exist'      => '菜单不存在',
        'create_success' => '菜单创建成功',
        'create_fail'    => '菜单创建失败',
        'update_success' => '菜单更新成功',
        'update_fail'    => '菜单更新失败',
        'delete_success' => '菜单删除成功',
        'delete_fail'    => '菜单删除失败',
        'move_success'   => '菜单移动成功',
        'move_fail'      => '菜单移动失败',
        'has_children'   => '存在子菜单，不可删除',
        'type'           => [
            'directory' => '目录',
            'menu'      => '菜单',
            'button'    => '按钮',
        ],
        'status'         => [
            'enabled'  => '启用',
            'disabled' => '禁用',
        ],
    ],

    // 数据字典
    'dict'         => [
        'title'          => '数据字典',
        'not_exist'      => '字典不存在',
        'create_success' => '字典创建成功',
        'create_fail'    => '字典创建失败',
        'update_success' => '字典更新成功',
        'update_fail'    => '字典更新失败',
        'delete_success' => '字典删除成功',
        'delete_fail'    => '字典删除失败',
        'item'           => [
            'not_exist'      => '字典项不存在',
            'create_success' => '字典项创建成功',
            'create_fail'    => '字典项创建失败',
            'update_success' => '字典项更新成功',
            'update_fail'    => '字典项更新失败',
            'delete_success' => '字典项删除成功',
            'delete_fail'    => '字典项删除失败',
        ],
    ],

    // 部门管理
    'dept'         => [
        'title'          => '部门管理',
        'not_exist'      => '部门不存在',
        'create_success' => '部门创建成功',
        'create_fail'    => '部门创建失败',
        'update_success' => '部门更新成功',
        'update_fail'    => '部门更新失败',
        'delete_success' => '部门删除成功',
        'delete_fail'    => '部门删除失败',
        'has_children'   => '存在子部门，不可删除',
        'has_users'      => '部门下存在用户，不可删除',
    ],

    // 职位管理
    'post'         => [
        'title'          => '职位管理',
        'not_exist'      => '职位不存在',
        'create_success' => '职位创建成功',
        'create_fail'    => '职位创建失败',
        'update_success' => '职位更新成功',
        'update_fail'    => '职位更新失败',
        'delete_success' => '职位删除成功',
        'delete_fail'    => '职位删除失败',
        'has_users'      => '职位下存在用户，不可删除',
    ],

    // 系统参数
    'config'       => [
        'title'          => '系统参数',
        'not_exist'      => '参数不存在',
        'update_success' => '参数更新成功',
        'update_fail'    => '参数更新失败',
        'group'          => [
            'system'   => '系统设置',
            'security' => '安全设置',
            'mail'     => '邮件设置',
            'sms'      => '短信设置',
            'storage'  => '存储设置',
        ],
        'cache_cleared'  => '配置缓存已清除',
    ],

    // 数据回收
    'recycle'      => [
        'title'           => '数据回收',
        'not_exist'       => '回收项不存在',
        'recycle_success' => '数据回收成功',
        'recycle_fail'    => '数据回收失败',
        'restore_success' => '数据恢复成功',
        'restore_fail'    => '数据恢复失败',
        'delete_success'  => '数据永久删除成功',
        'delete_fail'     => '数据永久删除失败',
        'empty_success'   => '回收站清空成功',
        'empty_fail'      => '回收站清空失败',
        'type'            => [
            'user'       => '用户',
            'role'       => '角色',
            'menu'       => '菜单',
            'dept'       => '部门',
            'post'       => '职位',
            'dict'       => '字典',
            'attachment' => '附件',
        ],
    ],

    // 操作日志
    'operate_log'  => [
        'title'          => '操作日志',
        'not_exist'      => '日志不存在',
        'clear_success'  => '日志清除成功',
        'clear_fail'     => '日志清除失败',
        'export_success' => '日志导出成功',
        'export_fail'    => '日志导出失败',
        'type'           => [
            'add'      => '新增',
            'edit'     => '编辑',
            'delete'   => '删除',
            'login'    => '登录',
            'logout'   => '退出',
            'upload'   => '上传',
            'download' => '下载',
        ],
        'result'         => [
            'success' => '成功',
            'fail'    => '失败',
        ],
    ],

    // 登录日志
    'login_log'    => [
        'title'          => '登录日志',
        'not_exist'      => '日志不存在',
        'clear_success'  => '日志清除成功',
        'clear_fail'     => '日志清除失败',
        'export_success' => '日志导出成功',
        'export_fail'    => '日志导出失败',
        'status'         => [
            'success' => '成功',
            'fail'    => '失败',
        ],
        'type'           => [
            'web'         => '网页登录',
            'mobile'      => '手机登录',
            'api'         => 'API登录',
            'third_party' => '第三方登录',
        ],
    ],

    // 定时任务
    'crontab'      => [
        'title'          => '定时任务',
        'not_exist'      => '任务不存在',
        'create_success' => '任务创建成功',
        'create_fail'    => '任务创建失败',
        'update_success' => '任务更新成功',
        'update_fail'    => '任务更新失败',
        'delete_success' => '任务删除成功',
        'delete_fail'    => '任务删除失败',
        'start_success'  => '任务启动成功',
        'start_fail'     => '任务启动失败',
        'stop_success'   => '任务停止成功',
        'stop_fail'      => '任务停止失败',
        'run_success'    => '任务执行成功',
        'run_fail'       => '任务执行失败',
        'status'         => [
            'enabled'  => '启用',
            'disabled' => '禁用',
            'running'  => '运行中',
        ],
        'type'           => [
            'command'  => '命令行',
            'callback' => '回调函数',
            'url'      => 'URL请求',
        ],
        'log'            => [
            'title'         => '任务日志',
            'not_exist'     => '日志不存在',
            'clear_success' => '日志清除成功',
            'clear_fail'    => '日志清除失败',
        ],
    ],
    // 消息管理
    'message'      => [
        'title'          => '消息管理',
        'not_exist'      => '消息不存在',
        'create_success' => '消息创建成功',
        'create_fail'    => '消息创建失败',
        'update_success' => '消息更新成功',
        'update_fail'    => '消息更新失败',
        'delete_success' => '消息删除成功',
        'delete_fail'    => '消息删除失败',
        'send_success'   => '消息发送成功',
        'send_fail'      => '消息发送失败',
        'read_success'   => '消息已读',
        'type'           => [
            'system'       => '系统消息',
            'notification' => '通知消息',
            'private'      => '私信',
        ],
        'status'         => [
            'unread'  => '未读',
            'read'    => '已读',
            'deleted' => '已删除',
        ],
    ],

    // 通知管理
    'notification' => [
        'title'           => '通知管理',
        'not_exist'       => '通知不存在',
        'create_success'  => '通知创建成功',
        'create_fail'     => '通知创建失败',
        'update_success'  => '通知更新成功',
        'update_fail'     => '通知更新失败',
        'delete_success'  => '通知删除成功',
        'delete_fail'     => '通知删除失败',
        'publish_success' => '通知发布成功',
        'publish_fail'    => '通知发布失败',
        'type'            => [
            'system'       => '系统通知',
            'announcement' => '公告',
            'reminder'     => '提醒',
        ],
        'status'          => [
            'draft'     => '草稿',
            'published' => '已发布',
            'expired'   => '已过期',
        ],
    ],

    // 附件管理
    'attachment'   => [
        'title'            => '附件管理',
        'not_exist'        => '附件不存在',
        'upload_success'   => '附件上传成功',
        'upload_fail'      => '附件上传失败',
        'download_success' => '附件下载成功',
        'download_fail'    => '附件下载失败',
        'delete_success'   => '附件删除成功',
        'delete_fail'      => '附件删除失败',
        'type'             => [
            'image'    => '图片',
            'document' => '文档',
            'video'    => '视频',
            'audio'    => '音频',
            'other'    => '其他',
        ],
        'size_exceed'      => '文件大小超出限制',
        'type_not_allowed' => '文件类型不允许',
    ],
];