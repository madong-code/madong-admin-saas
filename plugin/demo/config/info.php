<?php

/**
 * 插件信息配置
 */

return [
        'name' => 'demo',
        'identifier' => 'demo',
        'type' => 'madong:app',
        'version' => '1.1.0',
        'description' => '一款面向调试与测试的示例插件，模拟常见扩展功能逻辑，帮助开发者快速验证接口、样式、交互效果。
插件轻量化运行，无冗余依赖，兼容当前系统版本，安装即用。',
        'author' => 'Mr.April',
        'author_email' => '',
        'website' => 'https://madong.tech',
        'uninstall' => [
            'drop_tables' => true, //卸载时会删除 demo_test 数据表
            'remove_dependencies' => false, //卸载时移除 composer/npm 依赖
            'undeletable' => true, //系统内置插件，不可卸载、不可删除
        ],
    // 依赖列表(供平台端 /plugin/tenant-auth/dependencies 测试):
    //  - demo 自身已装 (installed.php) → satisfied (绿色)
    //  - user-management 在 plugin/ 目录不存在 → missing (红色)
    //  - data-export 故意未装 → missing (红色)
    'require'    => [
        'demo',
        'user-management',
        'data-export',
    ],
];
