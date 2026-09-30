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

namespace app\mcp;

use app\model\system\admin\Admin;
use app\model\system\menu\Menu;
use app\model\system\role\RoleMenu;
use core\communication\mcp\contract\IdentityResolver;
use core\communication\mcp\security\McpAuthException;
use core\communication\mcp\security\McpUser;
use core\security\jwt\ex\JwtException;
use core\security\jwt\JwtToken;
use support\Request;

/**
 * madong MCP 身份解析器：Bearer JWT -> McpUser
 *
 * 桥接 core\security\jwt\JwtToken 与 admin 菜单权限码（语义对齐 CurrentUser::getPermissions）；
 * 不复用 CurrentUser 静态入口（其依赖全局 request() 取 token），token 由本类显式提取。
 */
final class MadongIdentityResolver implements IdentityResolver
{
    /**
     * @throws McpAuthException token 无效/过期/黑名单，或身份不存在
     */
    public function resolve(Request $request): ?McpUser
    {
        $authorization = (string) $request->header('authorization', '');
        if (!preg_match('/^\s*Bearer\s+(\S+)\s*$/i', $authorization, $matches)) {
            return null;
        }

        try {
            $payload = (new JwtToken())->verify($matches[1]);
        } catch (JwtException $e) {
            throw new McpAuthException($e->getMessage(), 0, $e);
        }

        if (empty($payload)) {
            throw new McpAuthException('Invalid token payload');
        }

        $uid = (string) ($payload['id'] ?? '');
        if ($uid === '') {
            throw new McpAuthException('Invalid user in token');
        }

        /** @var Admin|null $admin */
        $admin = Admin::query()->find($uid);
        if ($admin === null) {
            throw new McpAuthException('Admin identity not found');
        }

        return new McpUser(
            id: $uid,
            permissions: $this->permissionsOf($admin),
            scopes: (array) ($payload['scopes'] ?? []),
            name: (string) ($admin->real_name ?? ''),
            tenantId: $this->tenantIdOf($payload, $admin),
        );
    }

    /**
     * 租户 ID：优先取登录时写入 token 的 current_tenant.id（SaaS），回退到管理员所属租户
     *
     * @param array $payload JwtToken::verify() 的负载
     */
    private function tenantIdOf(array $payload, Admin $admin): int|string|null
    {
        $tenantId = $payload['extra']['current_tenant']['id'] ?? null;
        if ($tenantId === null || $tenantId === '') {
            $tenantId = $admin->tenant_id ?? null;
        }

        return ($tenantId === null || $tenantId === '') ? null : $tenantId;
    }

    /**
     * 权限码集合（复用 CurrentUser::getPermissions 查询语义）
     *
     * @return string[]
     */
    private function permissionsOf(Admin $admin): array
    {
        if ($admin->isSuperAdmin()) {
            return ['*'];
        }

        $roleIds = $admin->roles()->pluck('sys_role.id')->toArray();
        if ($roleIds === []) {
            return [];
        }

        $connectionName = $admin->getConnectionName();
        $menuIds = RoleMenu::on($connectionName)
            ->whereIn('role_id', $roleIds)
            ->pluck('menu_id')
            ->unique()
            ->toArray();
        if ($menuIds === []) {
            return [];
        }

        return Menu::on($connectionName)
            ->whereIn('id', $menuIds)
            ->where('enabled', 1)
            ->whereNotNull('code')
            ->where('code', '<>', '')
            ->pluck('code')
            ->unique()
            ->values()
            ->toArray();
    }
}
