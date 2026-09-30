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
return [
    // 模块命名限制
    'module_name_restrictions' => [
        'enabled' => true,
        'reserved_names' => [
            'system', 'role', 'admin', 'auth', 'user', 'permission',
            'menu', 'dept', 'post', 'dict', 'config', 'log',
            'monitor', 'plugin', 'dev', 'test', 'generator'
        ],
        'pattern' => '/^[a-z0-9_-]+$/'
    ],

    // 场景类型定义
    // key = 场景标识, value = [label: 显示名, default_sub_path: 默认子路径, template_type: 模板类型目录名(空=后端)]
    'scene_types' => [
        'backend' => [
            'label' => '后端',
            'default_sub_path' => '',
            'template_type' => '',
        ],
        'admin' => [
            'label' => '管理端',
            'default_sub_path' => 'apps/admin',
            'plugin_sub_path' => 'apps/admin/src/plugin/{plugin}',
            'template_type' => 'mono',
        ],
        'platform' => [
            'label' => '平台端',
            'default_sub_path' => 'apps/platform',
            'plugin_sub_path' => 'apps/platform/src/plugin/{plugin}',
            'template_type' => 'mono',
        ],
        'web' => [
            'label' => '会员端',
            'default_sub_path' => '',
            'plugin_sub_path' => 'src/plugin/{plugin}',
            'template_type' => 'web',
        ],
        'h5' => [
            'label' => 'H5',
            'default_sub_path' => '',
            'plugin_sub_path' => 'src/plugin/{plugin}',
            'template_type' => 'h5',
        ],
    ],

    // 模板类型定义
    // key = 模板类型标识 = template/ 下的目录名
    // value = [label: 显示名, supported_scenes: 支持的用户 scene_type 列表(用于向后兼容验证)]
    'template_types' => [
        'mono' => [
            'label' => '前端(mono)',
            'supported_scenes' => ['backend', 'admin', 'platform', 'web', 'h5'],
        ],
        'web' => [
            'label' => 'Web前端',
            'supported_scenes' => ['web'],  
        ],
        'h5' => [
            'label' => 'H5',
            'supported_scenes' => ['h5'],
        ],
    ],

    // 默认文件类型映射（每种场景生成哪些文件）
    'file_types_map' => [
        'backend' => ['controller', 'model', 'service', 'dao', 'validate', 'request_form', 'request_query', 'response'],
        'admin'   => ['api', 'api_model', 'view', 'view_schema', 'lang'],
        'platform'=> ['api', 'api_model', 'view', 'view_schema', 'lang'],
        'web'     => ['api', 'api_model', 'view', 'view_schema', 'lang'],
        'h5'      => ['api', 'api_model', 'view', 'view_schema', 'lang'],
    ],

    // 默认生成场景（顺序控制生成顺序）
    'default_scene_types' => ['backend', 'admin'],
];
