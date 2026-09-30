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
 * ping：连通性测试工具（匿名可访问）
 */
final class PingTool
{
    public function __construct(
        private readonly ?McpUser $user = null,
    ) {
    }

    #[McpTool(
        name: 'ping',
        title: 'Ping',
        description: '连通性测试，恒定返回 pong。',
        inputSchema: ['type' => 'object', 'properties' => []],
        permission: null,
    )]
    public function ping(): string
    {
        return 'pong';
    }
}
