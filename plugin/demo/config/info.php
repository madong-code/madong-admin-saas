<?php

/**
 * 插件信息配置
 */

return [
        'name' => 'demo',
        'identifier' => 'demo',
        // syncPlugins 扫描注册所需字段（PluginDevelopService::syncPlugins 要求 key 与目录名一致）
        'key' => 'demo',
        'title' => 'demo 示例演示',
        'desc' => '一款面向调试与测试的示例插件，模拟常见扩展功能逻辑，帮助开发者快速验证接口、样式、交互效果。
插件轻量化运行，无冗余依赖，兼容当前系统版本，安装即用。',
        'support_app' => '',
        'status' => 1,
        'type' => 'madong:app',
        'version' => '1.0.0',
        'description' => '一款面向调试与测试的示例插件，模拟常见扩展功能逻辑，帮助开发者快速验证接口、样式、交互效果。
插件轻量化运行，无冗余依赖，兼容当前系统版本，安装即用。',
        'author' => 'Mr.April',
        'author_email' => '',
        'website' => 'https://madong.tech',
        'uninstall' => [
            'drop_tables' => true, //卸载时会删除 demo_test 数据表
            'remove_dependencies' => false,//卸载时移除 composer/npm 依赖
            'undeletable' => false,//卸载后无法删除
        ],
    ];
