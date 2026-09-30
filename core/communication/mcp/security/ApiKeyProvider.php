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

/**
 * X-Mcp-Api-Key 通道身份提供者（配置式，首期不落库）
 *
 * 从 config('mcp.auth.api_keys') 按 key 查表构造 McpUser，
 * 不依赖 CurrentUser（无 HTTP 会话语义），适合 CI/Agent 等机器身份。
 */
final class ApiKeyProvider
{
    /**
     * @throws McpAuthException key 未登记
     */
    public function resolve(string $apiKey): McpUser
    {
        $keys = (array) config('mcp.auth.api_keys', []);
        $identity = $keys[$apiKey] ?? null;
        if (!is_array($identity)) {
            throw new McpAuthException('Invalid MCP API key');
        }

        $tenantId = $identity['tenant_id'] ?? null;

        return new McpUser(
            id: $identity['id'] ?? 0,
            permissions: (array) ($identity['permissions'] ?? []),
            scopes: (array) ($identity['scopes'] ?? []),
            name: (string) ($identity['name'] ?? 'api-key'),
            tenantId: ($tenantId === null || $tenantId === '') ? null : $tenantId,
        );
    }
}
