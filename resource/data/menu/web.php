<?php
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

/**
 * 主应用前端菜单种子数据
 * 仅包含主应用 routes.ts 中 menu:true 的路由
 * 插件菜单由各自 resource/menu/web.php 管理
 *
 * category: 1=导航菜单(nav), 2=会员菜单(member)
 * type: 1=目录, 2=导航页, 3=外链, 4=单页
 */

return [
    // ==================== 导航菜单 (category=1) ====================
    [
        'app'      => 'web',
        'category' => 1,  // 导航菜单
        'source'   => 'system',
        'code'     => 'web:home',
        'name'     => '首页',
        'url'      => '/',
        'icon'     => 'mdi:home',
        'level'    => 1,
        'type'     => 2,  // 导航页
        'sort'     => 0,
        'target'   => 1,
        'is_show'   => 1,
        'is_public' => 1,
        'is_no_auth'=> 0,
        'enabled'   => 1,
        'created_at' => time(),
        'updated_at' => time(),
        'deleted_at' => null
    ],

    // ==================== 会员菜单 (category=2) ====================

    // 账户设置（目录父节点，仅需登录）
    [
        'app'      => 'web',
        'category' => 2,  // 会员菜单
        'source'   => 'system',
        'code'     => 'web:account_settings',
        'name'     => '账户设置',
        'url'      => null,
        'icon'     => 'mdi:account-cog',
        'level'    => 1,
        'type'     => 1,  // 目录
        'sort'     => 10,
        'target'   => 1,
        'is_show'  => 1,
        'is_public'=> 0,
        'is_no_auth'=> 1,
        'enabled'  => 1,
        'created_at' => time(),
        'updated_at' => time(),
        'deleted_at' => null,
        'children' => [
            [
                'app'      => 'web',
                'category' => 2,
                'source'   => 'system',
                'code'     => 'web:member:profile',
                'name'     => '个人资料',
                'url'      => '/member/profile',
                'icon'     => 'mdi:account-circle',
                'level'    => 1,
                'type'     => 2,
                'sort'     => 10,
                'target'   => 1,
                'is_show'  => 1,
                'is_public'=> 0,
                'is_no_auth'=> 1,
                'enabled'  => 1,
                'created_at' => time(),
                'updated_at' => time(),
                'deleted_at' => null
            ],
            [
                'app'      => 'web',
                'category' => 2,
                'source'   => 'system',
                'code'     => 'web:member:settings',
                'name'     => '用户设置',
                'url'      => '/member/settings',
                'icon'     => 'mdi:cog',
                'level'    => 1,
                'type'     => 2,
                'sort'     => 11,
                'target'   => 1,
                'is_show'  => 1,
                'is_public'=> 0,
                'is_no_auth'=> 1,
                'enabled'  => 1,
                'created_at' => time(),
                'updated_at' => time(),
                'deleted_at' => null
            ],
            [
                'app'      => 'web',
                'category' => 2,
                'source'   => 'system',
                'code'     => 'web:member:password',
                'name'     => '修改密码',
                'url'      => '/member/password',
                'icon'     => 'mdi:key-variant',
                'level'    => 1,
                'type'     => 2,
                'sort'     => 12,
                'target'   => 1,
                'is_show'  => 1,
                'is_public'=> 0,
                'is_no_auth'=> 1,
                'enabled'  => 1,
                'created_at' => time(),
                'updated_at' => time(),
                'deleted_at' => null
            ],
            [
                'app'      => 'web',
                'category' => 2,
                'source'   => 'system',
                'code'     => 'web:member:points',
                'name'     => '积分记录',
                'url'      => '/member/points',
                'icon'     => 'mdi:star-circle',
                'level'    => 1,
                'type'     => 2,
                'sort'     => 13,
                'target'   => 1,
                'is_show'  => 1,
                'is_public'=> 0,
                'is_no_auth'=> 0,
                'enabled'  => 1,
                'created_at' => time(),
                'updated_at' => time(),
                'deleted_at' => null
            ],
            [
                'app'      => 'web',
                'category' => 2,
                'source'   => 'system',
                'code'     => 'web:member:balance',
                'name'     => '余额记录',
                'url'      => '/member/balance',
                'icon'     => 'mdi:wallet',
                'level'    => 1,
                'type'     => 2,
                'sort'     => 14,
                'target'   => 1,
                'is_show'  => 1,
                'is_public'=> 0,
                'is_no_auth'=> 0,
                'enabled'  => 1,
                'created_at' => time(),
                'updated_at' => time(),
                'deleted_at' => null
            ],
            [
                'app'      => 'web',
                'category' => 2,
                'source'   => 'system',
                'code'     => 'web:member:sign',
                'name'     => '每日签到',
                'url'      => '/member/sign',
                'icon'     => 'mdi:calendar-check',
                'level'    => 1,
                'type'     => 2,
                'sort'     => 15,
                'target'   => 1,
                'is_show'  => 1,
                'is_public'=> 0,
                'is_no_auth'=> 0,
                'enabled'  => 1,
                'created_at' => time(),
                'updated_at' => time(),
                'deleted_at' => null
            ],
        ]
    ],
];


