<?php

return [
    'templates' => [
        // 文件回退模板：group_code 仅作语义标记，运行时按 code 匹配 content
        // 数据库种子见 resource/data/config/config.php（default / platform 两组）
        // 本地存储配置
        [
            'group_code' => 'default',
            'code' => 'local',
            'name' => '本地存储配置',
            'content' => [
                'root' => 'public',
                'dirname' => 'upload',
                'domain' => 'http://127.0.0.1:8500',
                // 是否按租户分目录：缺 key 时运行时默认 租户=true / 单体·平台=false
                'tenant_path' => true,
                'tenant_path_pattern' => 'tenant_{tenant_id}',
                // 本地存储与站点同域，恒为公开读（前端不展示私有开关）
                'is_private' => false,
                'ttl' => 3600,
            ],
            'is_sys' => 1
        ],
        // OSS存储配置
        [
            'group_code' => 'default',
            'code' => 'oss',
            'name' => 'OSS存储配置',
            'content' => [
                'accessKeyId' => '',
                'accessKeySecret' => '',
                'bucket' => '',
                'domain' => '',
                'endpoint' => '',
                'dirname' => '',
                'tenant_path' => true,
                'tenant_path_pattern' => 'tenant_{tenant_id}',
                // 私有空间（非公开读）：开启后前端不能自行拼接地址，需按 key 换取签名直链
                'is_private' => false,
                'ttl' => 3600,
            ],
            'is_sys' => 1
        ],
        // COS存储配置
        [
            'group_code' => 'default',
            'code' => 'cos',
            'name' => 'COS存储配置',
            'content' => [
                'secretId' => '',
                'secretKey' => '',
                'bucket' => '',
                'domain' => '',
                'region' => '',
                'dirname' => '',
                'tenant_path' => true,
                'tenant_path_pattern' => 'tenant_{tenant_id}',
                // 私有空间（非公开读）：开启后前端不能自行拼接地址，需按 key 换取签名直链
                'is_private' => false,
                'ttl' => 3600,
            ],
            'is_sys' => 1
        ],
        // 七牛云存储配置
        [
            'group_code' => 'default',
            'code' => 'qiniu',
            'name' => '七牛云存储配置',
            'content' => [
                'accessKey' => '',
                'secretKey' => '',
                'bucket' => '',
                'domain' => '',
                'region' => '',
                'dirname' => '',
                'tenant_path' => true,
                'tenant_path_pattern' => 'tenant_{tenant_id}',
                // 私有空间（非公开读）：开启后前端不能自行拼接地址，需按 key 换取签名直链
                'is_private' => false,
                'ttl' => 3600,
            ],
            'is_sys' => 1
        ],
        // S3存储配置
        [
            'group_code' => 'default',
            'code' => 's3',
            'name' => 'S3存储配置',
            'content' => [
                'key' => '',
                'secret' => '',
                'bucket' => '',
                'dirname' => '',
                'domain' => '',
                'region' => '',
                'version' => '',
                'endpoint' => '',
                'acl' => '',
                'tenant_path' => true,
                'tenant_path_pattern' => 'tenant_{tenant_id}',
                // 私有空间（非公开读）：开启后前端不能自行拼接地址，需按 key 换取签名直链
                'is_private' => false,
                'ttl' => 3600,
            ],
            'is_sys' => 1
        ],
    ],
];
