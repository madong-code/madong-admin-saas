<?php
/**
 * 插件消息数据示例
 *
 * 格式与 resource/data/message/category.php 一致：
 * 分类 > 定义 > 模板，安装到租户时按 tenant_id 隔离导入（source=plugin:demo）。
 */
return [
    [
        'key'         => 'demo_notice',
        'name'        => '示例通知',
        'icon'        => 'bell',
        'description' => 'demo 插件消息示例',
        'sort'        => 100,
        'definitions' => [
            [
                'key'         => 'demo_notice_push',
                'name'        => '示例推送',
                'description' => 'demo 插件的示例消息',
                'default_on'  => true,
                'nav_type'    => 'none',
                'nav_value'   => '',
                'sort'        => 10,
                'templates'   => [
                    [
                        'type'             => 'system',
                        'key'              => 'demo_notice_tpl',
                        'title'            => '示例通知',
                        'content_template' => '这是一条 demo 插件示例消息',
                    ],
                ],
            ],
        ],
    ],
];
