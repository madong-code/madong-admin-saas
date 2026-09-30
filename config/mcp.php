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
 * MCP Server 配置（应用层）
 *
 * webman 启动时自动加载为 config('mcp.*')，由 core/communication/mcp 实现读取。
 * MCP 面向开发联调场景（Trae 编辑器、CLI 等本地 MCP 客户端）；生产环境如未使用，
 * 将 enable 置为 false 即可（端点返回 404，不暴露存在性）。
 */
return [
    // 总开关：false 时端点返回 404
    'enable' => true,

    // MCP 端点路径（仅文档用途，实际路由注册见 config/route.php 引入的 core/communication/mcp/route.php）
    'endpoint' => '/mcp',

    // MCP 客户端 initialize 时返回的服务器标识
    'server_name' => 'madong',
    'server_version' => '1.0.0',

    // 会话存储：file（默认，FileSessionStore）| redis（RedisSessionStore，需 webman/redis）
    'session' => [
        'store' => 'file',
        'path' => runtime_path('mcp/sessions'),
        'ttl' => 3600,
    ],

    // 鉴权：双通道（X-Mcp-Api-Key 优先于 Bearer JWT），均无则匿名（仅可见无需鉴权工具）
    'auth' => [
        'jwt' => true,
        'api_key' => true,

        // key => 身份定义；当前仅登记本机开发联调身份（Trae 编辑器等 MCP 客户端使用）
        // tenant_id（SaaS 专属，可选）：该 key 绑定的租户；缺省/null 表示平台级（不注入租户上下文）。
        // 未绑定时仍可经请求头 X-Tenant-Id 指定租户，优先级见 core/communication/mcp/endpoint/McpEndpointController。
        'api_keys' => [
            'dev-editor-key-local' => [
                'id' => 0,
                'name' => 'dev-editor',
                'permissions' => ['*'],
                'scopes' => [],
                'tenant_id' => null,
            ],
        ],

        // Bearer JWT -> McpUser 的解析器
        'identity_resolver' => \app\mcp\MadongIdentityResolver::class,
    ],

    // 内置可选工具开关（关闭后调用返回 enabled=false 提示；工具仍会注册，便于客户端感知）
    'tools' => [
        'db_schema' => true,
        'log_tail'  => true,
    ],

    // 工具自动发现目录：相对路径基于 base_path()，支持 glob（plugin/*/app/mcp）
    'discovery' => [
        'dirs' => [
            'app/mcp',
            'plugin/*/app/mcp',
            __DIR__ . '/../core/communication/mcp/tool',
        ],
        // 工具清单缓存文件（按被扫目录文件 mtime 指纹自动失效）
        'manifest' => runtime_path('mcp/manifest.php'),
    ],
];
