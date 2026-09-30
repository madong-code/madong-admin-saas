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

namespace app\dao\system\role;

use app\model\system\role\Role;
use core\foundation\base\BaseDao;

/**
 *
 * 角色
 * @author Mr.April
 * @since  1.0
 */
class RoleDao extends BaseDao
{

    protected function setModel(): string
    {
        return Role::class;
    }

    /**
     * 通过角色获取菜单
     *
     * @param array $ids
     *
     * @return array
     * @throws \Exception
     */
    public function getMenuIdsByRoleIds(array $ids = []): array
    {
        if (empty($ids)) {
            return [];
        }
        $where = ['id' => $ids];
        return $this->selectList($where, '*', 0, 0, '', ['menus' => function ($query) {
            $query->where('enabled', 1);
        }], true)->toArray();
    }

    /**
     * 获取角色权限集合-
     *
     * @return array
     * @throws \Exception
     * /* 最终结果
     * [
     * 'roleId1' => [
     * 'menu'   => [101, 102],
     * 'button => [201],
     * 'api'    => [301, 302]
     * ],
     * 'roleId2' => [
     * 'menu'   => [103],
     * 'button' => [],
     * 'api'    => [303]
     * ]
     * ]
     */
    public function getRolePermissions(): array
    {
        $result = $this->getModel()
            ->withoutGlobalScope('TenantScope')
            ->where('enabled', 1)
            ->with(['menus'])
            ->orderBy('sort', 'asc')
            ->get([
                "id",
                "tenant_id",
                "pid",
                "name",
                "code",
                "is_super_admin",
                "role_type",
                "data_scope",
                "enabled",
                "sort",
            ])
            ->toArray();

        // 初始化结构化数组
        $rolePermissions = [];
        foreach ($result as $role) {
            $roleId = $role['id'];
            // 初始化当前角色的权限集合
            $rolePermissions[$roleId] = [
                'id'         => $role['id'] ?? '',
                'name'       => $role['name'] ?? '',
                'tenant_id'  => $role['tenant_id'] ?? '',
                'data_scope' => $role['data_scope'] ?? 1,
                'menu'       => [],
                'button'     => [],
                'api'        => [],
            ];

            // 遍历关联的 menus 数据
            if (!empty($role['menus'])) {
                foreach ($role['menus'] as $menu) {
                    switch ($menu['type']) {
                        case 1:
                        case 2:
                            // 类型 1 或 2 是菜单，添加到 menu
                            $rolePermissions[$roleId]['menu'][] = $menu['id'];
                            break;
                        case 3:
                            // 类型 3 是按钮，添加到 button
                            $rolePermissions[$roleId]['button'][] = $menu['id'];
                            break;
                        case 4:
                            // 类型 4 是接口，添加到 api
                            $rolePermissions[$roleId]['api'][] = $menu['id'];
                            break;
                        default:
                            // 未知类型，忽略或记录日志
                            break;
                    }
                }
            }
        }
        return $rolePermissions ?? [];
    }

    /**
     * 角色列表
     *
     * @param array      $where
     * @param string     $field
     * @param int        $page
     * @param int        $limit
     * @param string     $order
     * @param array      $with
     * @param bool       $search
     * @param array|null $withoutScopes
     *
     * @return \Illuminate\Database\Eloquent\Collection|null
     * @throws \Exception
     */
    public function selectList(array $where, string|array $field = '*', int $page = 0, int $limit = 0, string $order = '', array $with = [], bool $search = false, ?array $withoutScopes = null): ?\Illuminate\Database\Eloquent\Collection
    {
        // 不再加载 casbin 关联，使用默认的 with 参数
        return parent::selectList($where, $field, $page, $limit, $order, $with, $search, $withoutScopes);
    }

    /**
     * @param int|string  $id
     * @param string|null $tenantId
     *
     * @return \app\model\system
ole\Role|null
     * @throws \Exception
     */
    public function getImplicitPermissionsForRole(int|string $id, string|null $tenantId = null): ?Role
    {
        // 获取 tenantId，如果未提供且租户功能启用，则从上下文中获取
        if (config('tenant.enabled', false) && empty($tenantId)) {
            $tenantId = TenantContext::getTenantId();
        }
        // 构建查询
        return $this->getModel()->query()
            ->withoutGlobalScope('TenantScope')
            ->where('enabled', 1)
            ->when($tenantId, function ($query) use ($tenantId) {
                $query->where('tenant_id', $tenantId);
            })
            ->with(['permissions'])
            ->orderBy('sort')
            ->first();
    }

}
