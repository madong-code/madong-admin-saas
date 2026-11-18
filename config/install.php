<?php
declare(strict_types=1);

/**
 *+------------------
 * madong
 *+------------------
 * Copyright (c) https://gitee.com/motion-code  All rights reserved.
 *+------------------
 * Author: Mr. April (405784684@qq.com)
 * Official Website: http://www.madong.tech
 */

/**
 * 系统安装 / 插件安装统一配置
 *
 * 设计原则（模式决定路径、模板仅声明元数据、插件分发只认模板名，三者解耦）：
 *  - modes[模式].templates：模板名 => 相对 template/ 的物理路径。
 *    mono 模式指向单仓 apps 子目录（共享依赖）；separated 模式指向各自独立目录。
 *    新增一种组织方式只需在此增加一组映射，无需改加载代码。
 *  - common_templates：两种模式都包含的通用模板（web）。
 *  - template_meta：模板展示信息与插件落盘子目录，不含模式相关路径。
 *  - plugin_distribution：插件默认分发目标与安装环节顺序。
 *
 * 路径查找：templatePath(模板, 模式) = template/ + modes[模式].templates[模板]，
 * 未命中时回退 common_templates[模板]。统一由 core\business\install\InstallConfigResolver 解析。
 */
return [
    // 默认运行模式：separated（分离双端）/ mono（单仓双端）
    // 可通过环境变量覆盖，未配置时默认 separated
    'default_mode' => env('install.default_mode', 'separated'),

    // 运行模式定义：每个模式自带「模板名 => 相对 template/ 路径」映射
    'modes' => [
        'mono' => [
            'label' => '单仓双端',
            'templates' => [
                'admin' => 'mono/apps/admin',
                'platform' => 'mono/apps/platform',
            ],
        ],
        'separated' => [
            'label' => '分离双端',
            'templates' => [
                'admin' => 'admin',
                'platform' => 'platform',
            ],
        ],
    ],

    // 通用模板：两种模式都包含（相对 template/ 路径）
    'common_templates' => [
        'web' => 'web',
    ],

    // 模板元数据：只声明展示信息、插件落盘子目录与构建产物子目录，不含模式相关路径
    // plugin_dir 需与前端插件扫描目录一致（web 为 Nuxt srcDir:'src' 下的 src/plugin）
    // build_dir 为构建产物相对模板根的目录：admin/platform 为 Vite 的 dist，web 为 Nuxt 的 .output/public
    'template_meta' => [
        'admin' => [
            'label' => '管理端',
            'plugin_dir' => 'src/plugin',
            'build_dir' => 'dist',
            'default' => true,
        ],
        'platform' => [
            'label' => '平台端',
            'plugin_dir' => 'src/plugin',
            'build_dir' => 'dist',
            'default' => true,
        ],
        'web' => [
            'label' => '会员端',
            'plugin_dir' => 'src/plugin',
            'build_dir' => '.output/public',
            'default' => false,
        ],
    ],

    // 插件分发规则
    'plugin_distribution' => [
        // 插件默认分发到 admin + platform 双端共用
        'default_templates' => ['admin', 'platform'],
        // 安装环节顺序：后端迁移 → 菜单 → 前端模板分发 → 标记（标记必须最后）
        'install_order' => ['backend', 'menus', 'frontend', 'mark'],
    ],
];
