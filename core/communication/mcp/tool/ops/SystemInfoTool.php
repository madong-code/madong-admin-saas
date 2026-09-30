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

namespace core\communication\mcp\tool\ops;

use core\communication\mcp\attribute\McpTool;
use core\communication\mcp\security\McpUser;

/**
 * system_info：系统信息工具（匿名可访问，用于连通性验证）
 */
final class SystemInfoTool
{
    public function __construct(
        private readonly ?McpUser $user = null,
    ) {
    }

    #[McpTool(
        name: 'system_info',
        title: 'System Info',
        description: '获取 madong 系统运行信息。section 可选：overview（默认概要）/ php / time。',
        inputSchema: [
            'type' => 'object',
            'properties' => [
                'section' => [
                    'type' => 'string',
                    'enum' => ['overview', 'php', 'time'],
                    'description' => '信息分区',
                ],
            ],
        ],
        permission: null,
    )]
    public function systemInfo(string $section = 'overview'): array
    {
        return match ($section) {
            'php' => [
                'php_version' => PHP_VERSION,
                'sapi' => php_sapi_name(),
                'loaded_extensions' => get_loaded_extensions(),
            ],
            'time' => [
                'server_time' => date('Y-m-d H:i:s'),
                'timezone' => date_default_timezone_get(),
                'timestamp' => time(),
            ],
            default => [
                'app' => 'madong',
                'mcp_server' => (string) config('mcp.server_name', 'madong'),
                'server_version' => (string) config('mcp.server_version', '1.0.0'),
                'php_version' => PHP_VERSION,
                'server_time' => date('Y-m-d H:i:s'),
            ],
        };
    }
}
