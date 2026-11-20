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

/**
 * 安装与前端资源统一配置
 *
 * 物理目录只有两种结构：
 *  - mono：template/mono（含 apps/admin、apps/platform）+ template/web
 *  - separated：template/admin（single/aura 来源统一重命名）+ template/platform + template/web
 *
 * 配置分层：
 *  - modes / common_templates：只决定「逻辑模板 => template/ 下的物理路径」
 *  - template_meta：只放展示名、插件目录、构建产物目录
 *  - download：集中管理「怎么拉取」（ZIP/Git、镜像源、仓库、分支、admin UI 来源），其他配置只引用仓库 key
 *  - plugin_distribution：插件分发目标与安装顺序
 *
 * 统一由 core\business\install\InstallConfigResolver 解析，安装服务与下载命令共用同一套结果。
 */
return [
    /**
     * 安装模式定义（可视化安装向导与 CLI 安装命令共用）
     *
     * - default：默认安装模式，未显式选择时生效
     * - options：可选值枚举，键名即合法模式值（mono / separated），
     *   每项包含展示名 label、模式说明 description、模板目录 templates、关联应用 apps
     *
     * 由 core\business\install\InstallConfigResolver::installModeOptions() 解析，
     * /install/mode 接口下发给前端向导渲染模式选择卡片。
     */
    'install_mode' => [
        // 默认值：separated（多仓模式）
        'default' => env('install.default_mode', 'separated'),

        // 可选值枚举与模式说明
        'options' => [
            'mono' => [
                'label'       => 'mono（大仓模式）',
                'description' => '前后端模板来自同一个大仓仓库：管理端/平台端位于 template/mono/apps/{admin,platform}，会员端位于 template/web',
                // 模板寻址：mono 模式使用 template/mono、template/web 两处模板目录
                'templates'   => ['template/mono', 'template/web'],
                // 关联应用：/apps/ 下的 admin、platform
                'apps'        => ['admin', 'platform'],
            ],
            'separated' => [
                'label'       => 'separated（多仓模式）',
                'description' => '各端模板独立存放：管理端 template/admin、平台端 template/platform、会员端 template/web',
                // 模板寻址：separated 模式使用 template/admin、template/platform、template/web 三处模板目录
                'templates'   => ['template/admin', 'template/platform', 'template/web'],
                // 关联应用：admin、platform（各自独立仓库）
                'apps'        => ['admin', 'platform'],
            ],
        ],
    ],

    // 默认运行模式：separated（多仓分离双端）/ mono（大仓单仓双端）；与 install_mode.default 保持一致
    'default_mode' => env('install.default_mode', 'separated'),

    // 模式只决定模板的物理路径，不涉及仓库（与 install_mode.options 一一对应）
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

    // 两种模式都包含的通用模板
    'common_templates' => [
        'web' => 'web',
    ],

    // 模板元数据：展示名、插件目录、构建产物目录、public 输出子目录，不含模式相关路径
    'template_meta' => [
        'admin' => [
            'label' => '管理端',
            'plugin_dir' => 'src/plugin',
            'build_dir' => 'dist',
            // 构建产物输出到 backend/public/admin
            'output_dir' => 'admin',
            'default' => true,
        ],
        'platform' => [
            'label' => '平台端',
            'plugin_dir' => 'src/plugin',
            'build_dir' => 'dist',
            // 构建产物输出到 backend/public/platform（仅多租户模式构建）
            'output_dir' => 'platform',
            'default' => true,
        ],
        'web' => [
            'label' => '会员端',
            'plugin_dir' => 'src/plugin',
            'build_dir' => '.output/public',
            // 构建产物输出到 backend/public/web
            'output_dir' => 'web',
            'default' => false,
        ],
    ],

    /**
     * 构建输出规则（用户勾选「构建前端」时生效）
     *
     * - single_tenant：未启用多租户，只构建并输出 admin、web 两个应用
     * - multi_tenant：启用多租户，构建并输出 admin、platform、web 等所有已定义多端应用
     *
     * 由 InstallConfigResolver::buildTemplates() 解析；显式 --templates 只能收窄清单，
     * 不能突破这里的租户规则（例如单租户下不会构建 platform）。
     */
    'build_output' => [
        'single_tenant' => ['admin', 'web'],
        'multi_tenant'  => ['admin', 'platform', 'web'],
    ],

    // 下载配置：每个镜像源独立维护完整仓库地址，使用相同的 repo key 对应
    'download' => [
        'default_branch' => env('install.download_branch', 'main'),

        // 默认用 Git 克隆下载；命令 --method=zip 可切回归档下载
        'method' => env('install.download_method', 'git'),
        'archive_templates' => [
            'github.com'  => 'https://github.com/{path}/archive/refs/heads/{branch}.zip',
            'gitee.com'   => 'https://gitee.com/{path}/repository/archive/{branch}.zip',
            'gitcode.com' => 'https://gitcode.com/{path}/archive/{branch}.zip',
        ],
        // 默认 GitHub，仅使用主镜像拉取；镜像兜底由 fallback_enabled 控制
        'mirror' => env('install.download_mirror', 'github'),
        // 镜像兜底开关：默认关闭（不自动回退到其他仓库）；开启后缺失的仓库按 fallback_mirrors 顺序回退
        'fallback_enabled' => env('install.download_fallback', false),
        'fallback_mirrors' => ['gitee', 'gitcode'],
        'mirrors' => [
            'github' => [
                'label' => 'GitHub 官方源',
                'repositories' => [
                    'mono'     => 'https://github.com/madong-code/madong-mono.git',
                    'single'   => 'https://github.com/madong-code/madong-single.git',
                    'aura'     => 'https://github.com/madong-code/madong-aura.git',
                    'platform' => 'https://github.com/madong-code/madong-single-platform.git',
                    'web'      => 'https://github.com/madong-code/web-nuxt.git',
                    'skills'   => 'https://github.com/madong-code/madong-admin-saas-skills.git',
                ],
            ],
            'gitee' => [
                'label' => 'Gitee 国内镜像',
                'no_proxy' => true,
                'repositories' => [
                    'mono'     => 'https://gitee.com/motion-code/madong-mono.git',
                    'single'   => 'https://gitee.com/motion-code/madong-single.git',
                    'aura'     => 'https://gitee.com/motion-code/madong-aura.git',
                    'platform' => 'https://gitee.com/motion-code/madong-single-platform.git',
                    'web'      => 'https://gitee.com/motion-code/web-nuxt.git',
                    'skills'   => 'https://gitee.com/motion-code/madong-admin-saas-skills.git',
                ],
            ],
            'gitcode' => [
                'label' => 'GitCode 国内镜像',
                'no_proxy' => true,
                'repositories' => [
                    'mono'     => 'https://gitcode.com/motion-code/madong-mono.git',
                    'single'   => 'https://gitcode.com/motion-code/madong-single.git',
                    'aura'     => 'https://gitcode.com/motion-code/madong-aura.git',
                    'platform' => 'https://gitcode.com/motion-code/madong-single-platform.git',
                    'web'      => 'https://gitcode.com/motion-code/web-nuxt.git',
                    'skills'   => 'https://gitcode.com/motion-code/madong-admin-saas-skills.git',
                ],
            ],
        ],

        // admin 默认 UI 来源；命令 --admin-variant 可覆盖
        'admin_variant' => env('install.admin_variant', 'single'),
        'admin_variants' => [
            'single' => ['label' => '管理端（single）', 'repo' => 'single'],
            'aura'   => ['label' => '管理端（aura）',   'repo' => 'aura'],
        ],

        // 单仓模式下 admin/platform 位于同一个仓库，只克隆一次到 template/<path>
        'mode_repositories' => [
            'mono' => ['repo' => 'mono', 'path' => 'mono'],
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
