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
 * MCP 调用者身份值对象
 *
 * 权限语义对齐 app\adminapi\CurrentUser：菜单权限码数组，超级管理员为 ['*']。
 * 工具实例化时注入，方法内禁止读全局 request() 取身份。
 *
 * tenantId 为 SaaS 专属扩展：该身份绑定的租户 ID（null=平台级），
 * 由端点据此注入租户上下文，工具层无需感知。
 */
final class McpUser
{
    public function __construct(
        public readonly int|string $id,
        public readonly array $permissions = [],
        public readonly array $scopes = [],
        public readonly string $name = '',
        public readonly int|string|null $tenantId = null,
    ) {
    }

    /**
     * 权限判定（复用 CurrentUser 语义）：['*'] 恒真；and/or 运算
     *
     * @param string|array $codes     菜单权限码（数组时按 $operation 判定）
     * @param string       $operation and=全部满足；or=任一满足
     */
    public function can(string|array $codes, string $operation = 'and'): bool
    {
        if (in_array('*', $this->permissions, true)) {
            return true;
        }

        $codes = is_array($codes) ? $codes : [$codes];
        if ($codes === []) {
            return true;
        }

        $operation = strtolower($operation);
        if ($operation === 'or') {
            foreach ($codes as $code) {
                if (in_array($code, $this->permissions, true)) {
                    return true;
                }
            }
            return false;
        }

        foreach ($codes as $code) {
            if (!in_array($code, $this->permissions, true)) {
                return false;
            }
        }
        return true;
    }

    public function isSuperAdmin(): bool
    {
        return in_array('*', $this->permissions, true);
    }
}
