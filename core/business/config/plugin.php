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
 * 插件默认配置
 * 此文件的配置可被插件的 config/info.php 覆盖
 * 覆盖规则：info.php 中的配置会合并到此文件的配置中
 */

return [
    /**
     * 安装配置
     */
    'install'      => [
        // 是否自动创建日志目录
        'auto_mkdir_logs' => true,
    ],

    /**
     * 卸载配置
     */
    'uninstall'    => [
        // 卸载时是否删除数据表（默认 false）
        'drop_tables'         => false,
        // 卸载时是否移除合并的依赖（默认 false）
        'remove_dependencies' => false,
    ],

    /**
     * 模板资源配置
     * 插件模板复制目标位置（相对于项目根目录，即 server 的兄弟节点）
     * 结构: ['端' => '相对路径']
     */
    'template'     => [
        // 后台(租户/无租户)模板目标路径
        'admin'    => 'template/mono/apps/admin/src/plugin',
        // 平台管理后台模板目标路径
        'platform' => 'template/mono/apps/platform/src/plugin',
        // 前台模板目标路径
        'web'      => 'template/web/app/plugin',
    ],

    /**
     * 资源目录配置
     * 插件资源目录名称
     */
    'resource'     => [
        // 数据库迁移目录
        'migration' => 'database/migrations',
        // 数据库种子目录
        'seed'      => 'database/seeds',
        // 菜单资源目录
        'menu'      => 'menu',
        // 模板资源目录
        'template'  => 'template',
    ],

    /**
     * Logo 配置
     */
    'logo'         => [
        // Logo 存储目录（相对于项目根目录）
        'path'    => 'resource/images/plugin/logo',
        // 默认 logo 文件名
        'default' => 'default.png',
        // 支持的扩展名
        'ext'     => ['png', 'jpg', 'jpeg', 'svg', 'webp'],
    ],

    /**
     * 依赖合并配置
     */
    'dependencies' => [
        // 后台依赖配置 (admin/package.json)
        'admin'   => [
            'enabled'    => true,
            'merge_dev'  => true,
            'merge_prod' => true,
        ],
        // 平台管理后台依赖配置 (platform/package.json) [新增]
        'platform' => [
            'enabled'    => true,
            'merge_dev'  => true,
            'merge_prod' => true,
        ],
        // 前台依赖配置 (web/package.json)
        'web'     => [
            'enabled'    => true,
            'merge_dev'  => true,
            'merge_prod' => true,
        ],
        // 后端依赖配置 (server/composer.json)
        'backend' => [
            'enabled'           => true,
            'merge_require'     => true,
            'merge_require_dev' => true,
        ],
    ],

    /**
     * 数据库配置
     */
    'database'     => [
        // 连接名（默认使用框架配置）
        'connection' => null,
    ],
];
