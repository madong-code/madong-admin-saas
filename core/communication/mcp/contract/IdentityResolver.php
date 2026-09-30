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

namespace core\communication\mcp\contract;

use core\communication\mcp\security\McpUser;
use support\Request;

/**
 * MCP 身份解析器契约（core 不依赖 app）
 *
 * 具体实现（如 app\mcp\MadongIdentityResolver）桥接 JwtToken + 权限码解析，
 * 经 config('mcp.auth.identity_resolver') 注入 Authenticator。
 */
interface IdentityResolver
{
    /**
     * 由 webman Request 解析调用者身份
     *
     * @return McpUser|null 无凭证返回 null（匿名，仅可访问无需鉴权工具）；
     *                      有凭证但无效时抛出 core\communication\mcp\security\McpAuthException
     */
    public function resolve(Request $request): ?McpUser;
}
