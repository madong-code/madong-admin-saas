<?php

/**
 * Playground（演示/沙盒）环境配置
 *
 * enable:      是否开启限制，未配置或为 false 时默认为正式环境
 *
 * bypass_uids: 跳过限制的用户 ID 列表，匹配的用户可正常操作
 *              默认 [1] 仅 root 用户
 *
 * routes:      受限制的路由规则列表（正则表达式格式）
 *              匹配的路由将受 methods 配置的 HTTP 方法限制
 *
 * methods:     限制的 HTTP 方法
 *
 * route_methods: 按路由单独指定的限制方法（键为正则路由, 值为方法数组）
 *                优先于全局 methods, 适用于 GET 类敏感操作（如插件安装/卸载）
 *                注意: 我的应用(租户分发 tenant-auth)的安装/卸载/授权不受限
 *
 * message:     限制提示信息
 */
return [
    'enable'      => false,
    'bypass_uids' => [],
    'routes'  => [
        // 系统管理
        '/adminapi/system/user',
        '/adminapi/system/user/\d+',
        '/adminapi/system/menu',
        '/adminapi/system/menu/\d+',
        '/adminapi/system/role',
        '/adminapi/system/role/\d+',
        '/adminapi/system/dept/',
        '/adminapi/system/dept/\d+',
        '/adminapi/system/post',
        '/adminapi/system/post/\d+',
        '/adminapi/system/dict',
        '/adminapi/system/dict/\d+',
        '/adminapi/system/dict-item',
        '/adminapi/system/dict-item/\d+',
        '/adminapi/system/recycle-bin',
        '/adminapi/system/recycle-bin/\d+',
        '/adminapi/system/config',
        // 运维管理
        '/adminapi/ops/crontab',
        '/adminapi/ops/crontab/\d+',
        // 平台管理
        '/adminapi/platform/db',
        '/adminapi/platform/tenant-subscription',
        '/adminapi/platform/tenant-subscription/\d+',
        '/adminapi/platform/tenant-member',
        '/adminapi/platform/tenant-member/\d+',
        // 代码生成（完整 CRUD + 部署）
        '/adminapi/generator/code',
        '/adminapi/generator/code/\d+',
        '/adminapi/generator/code/\d+/deploy',
        '/adminapi/generator/code/\d+/preview',
        '/adminapi/generator/code/\d+/download',
        // 模块市场（安装/卸载/删除）
        '/adminapi/plugin/\w+/install',
        '/adminapi/plugin/\w+/uninstall',
        '/adminapi/plugin/\w+',
        '/adminapi/plugin',
        // 插件开发（新增/编辑/删除/打包）
        '/adminapi/plugin/develop',
        '/adminapi/plugin/develop/\d+',
        '/adminapi/plugin/develop/\d+/build',
        // WEB终端
        '/adminapi/devtools/terminal',
        '/adminapi/devtools/terminal/\d+',
        // 平台端-租户
        '/platformapi/tenant/account/\d+',
        '/platformapi/tenant/\d+/member/\d+',
        // 平台端-数据中心
        '/platformapi/db-settings',
        '/platformapi/db-settings/\d+',
        // ======== 平台端新增 ========
        // 1. 平台菜单（新增/编辑/删除）
        '/platformapi/platform-menu',
        '/platformapi/platform-menu/\d+',
        // 2. 菜单模板（新增/编辑/删除）
        '/platformapi/template/menu',
        '/platformapi/template/menu/\d+',
        '/platformapi/template/menu/tree',
        '/platformapi/template/menu/batch-delete',
        '/platformapi/template/menu/batch-store',
        // 2.1 字典模板（新增/编辑/删除）
        '/platformapi/template/dict',
        '/platformapi/template/dict/\d+',
        '/platformapi/template/dict/batch-delete',
        // 2.2 字典项模板（新增/编辑/删除）
        '/platformapi/template/dict-item',
        '/platformapi/template/dict-item/\d+',
        '/platformapi/template/dict-item/batch-delete',
        // 2.3 配置模板（新增/编辑/删除）
        '/platformapi/template/config',
        '/platformapi/template/config/\d+',
        '/platformapi/template/config/batch-delete',
        // 2.4 前台菜单模板（新增/编辑/删除）
        '/platformapi/template/web-menu',
        '/platformapi/template/web-menu/\d+',
        '/platformapi/template/web-menu/tree',
        '/platformapi/template/web-menu/batch-delete',
        // 3. 模块市场（安装/卸载/删除）
        //    安装/卸载为 GET 请求, 在 route_methods 中单独限制
        //    删除为 DELETE, 此处 \w+ 覆盖; 我的应用(tenant-auth 含 '-')不匹配, 不受限
        '/platformapi/plugin/\w+/install',
        '/platformapi/plugin/\w+/uninstall',
        '/platformapi/plugin/\w+',
        // 插件开发（新增/编辑/删除/打包）
        '/platformapi/plugin/develop',
        '/platformapi/plugin/develop/\d+',
        '/platformapi/plugin/develop/\d+/build',
        // 4. 代码生成部署
        '/platformapi/generator/code/\d+/deploy',
        // 5. WEB终端（执行命令/更新配置）
        '/platformapi/terminal/config',
        '/platformapi/terminal/execute',
        '/platformapi/terminal',
    ],
    'methods' => ['PUT', 'POST', 'DELETE'],
    // 按路由单独指定的限制方法（覆盖全局 methods）
    'route_methods' => [
        // 平台端模块市场 安装/卸载（GET 请求, 全局 methods 不含 GET）
        '/platformapi/plugin/\w+/install'   => ['GET'],
        '/platformapi/plugin/\w+/uninstall' => ['GET'],
    ],
    'message' => '演示环境,不支持当前操作',
];
