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

namespace core\communication\mcp\tool\system;

use core\communication\mcp\attribute\McpTool;
use core\communication\mcp\security\McpUser;

/**
 * current_user：当前调用者身份工具（permission=false：仅需鉴权，不要求权限码）
 */
final class CurrentUserTool
{
    public function __construct(
        private readonly ?McpUser $user = null,
    ) {
    }

    #[McpTool(
        name: 'current_user',
        title: 'Current User',
        description: '返回当前 MCP 调用者身份（id/名称/权限码概要），用于验证鉴权链路。',
        inputSchema: ['type' => 'object', 'properties' => []],
        permission: false,
    )]
    public function currentUser(): array
    {
        if ($this->user === null) {
            return ['authenticated' => false];
        }

        return [
            'authenticated' => true,
            'id' => (string) $this->user->id,
            'name' => $this->user->name,
            'is_super_admin' => $this->user->isSuperAdmin(),
            'permission_count' => count($this->user->permissions),
            'scopes' => $this->user->scopes,
        ];
    }
}
