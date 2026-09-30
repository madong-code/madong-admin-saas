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

namespace core\communication\mcp\security;

use support\Request;

/**
 * MCP 双通道鉴权编排器
 *
 * 顺序：X-Mcp-Api-Key（配置式）优先 -> Authorization: Bearer（identity_resolver 解析）
 * -> 均无则匿名（返回 null）。
 * 任一通道提供了凭证但解析失败，抛 McpAuthException（端点转 401），不静默降级为匿名。
 */
final class Authenticator
{
    /**
     * @throws McpAuthException 凭证无效 / 通道关闭但凭证存在 / 解析器未配置
     */
    public function authenticate(Request $request): ?McpUser
    {
        $auth = (array) config('mcp.auth', []);

        $apiKey = trim((string) $request->header('x-mcp-api-key', ''));
        if ($apiKey !== '') {
            if (empty($auth['api_key'])) {
                throw new McpAuthException('MCP API key authentication is disabled');
            }
            return (new ApiKeyProvider())->resolve($apiKey);
        }

        $authorization = (string) $request->header('authorization', '');
        if (preg_match('/^\s*Bearer\s+(\S+)\s*$/i', $authorization, $matches)) {
            if (empty($auth['jwt'])) {
                throw new McpAuthException('MCP JWT authentication is disabled');
            }
            $resolverClass = (string) ($auth['identity_resolver'] ?? '');
            if ($resolverClass === '' || !class_exists($resolverClass)) {
                throw new McpAuthException('MCP identity resolver is not configured');
            }
            $resolver = new $resolverClass();
            $user = $resolver->resolve($request);
            if ($user === null) {
                throw new McpAuthException('MCP identity could not be resolved');
            }
            return $user;
        }

        return null;
    }
}
