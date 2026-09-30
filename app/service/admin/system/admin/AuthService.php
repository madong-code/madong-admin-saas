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

namespace app\service\admin\system\admin;

use app\adminapi\CurrentUser;
use app\dao\system\admin\AdminDao;
use app\model\system\role\RoleMenu;
use core\foundation\base\BaseService;
use core\business\tenant\context\TenantContext;
use Illuminate\Database\Eloquent\Collection;
use app\service\admin\system\menu\MenuService;
use support\Container;

class AuthService extends BaseService
{

    public function __construct(AdminDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 获取菜单
     *
     * @param \app\adminapi\CurrentUser $currentUser
     * @param bool                      $includeButtons
     *
     * @return \Illuminate\Database\Eloquent\Collection|null
     * @throws \Exception
     */
    public function getMenusByUserRoles(CurrentUser $currentUser, bool $includeButtons = false): ?Collection
    {
        $adminModel   = $currentUser->admin();
        if (!$adminModel) {
            return new Collection();
        }
        
        $isSuperAdmin = boolval($adminModel->getAttribute('is_super'));
        /** @var MenuService $menuService */
        $menuService = Container::make(MenuService::class);
        $types       = $includeButtons ? [1, 2, 3, 4] : [1, 2];

        if ($isSuperAdmin) {
            // 超级管理员：仍按当前租户上下文隔离（字段隔离只返回当前租户菜单 + 公共菜单），
            // 不再跳过滤租户隔离，避免收集到其它租户的权限。
            $query = $menuService->dao->getModel()
                ->where('enabled', 1)
                ->whereIn('type', $types);
            $menuService->applyTenantFilter($query);
            return $query->orderBy('sort', 'asc')->get();
        }
        
        // 普通成员 - 通过角色获取菜单
        $menuIds = $this->getUserMenuIds($adminModel);
        return $this->getMenusByIds($menuService, array_unique($menuIds), $includeButtons);
    }
    
    /**
     * 获取用户拥有的菜单ID列表
     *
     * @param \app\model\system\Admin $adminModel
     * @return array 菜单ID数组
     */
    private function getUserMenuIds($adminModel): array
    {
        // 获取用户关联的角色ID（roles() 的 Role 模型已有 TenantScope，过滤出当前租户的角色）
        $roleIds = $adminModel->roles()->pluck('sys_role.id')->toArray();
        if (empty($roleIds)) {
            return [];
        }
        
        // 直接查 sys_role_menu 中间表获取 menu_id，绕过 Menu 模型的 TenantScope
        // （Menu 的全局 TenantScope 只做 WHERE tenant_id = X，会过滤掉 tenant_id IS NULL 的公共菜单）
        // 使用 Admin 模型的连接名，确保分库模式下与角色在同一数据库
        $connectionName = $adminModel->getConnectionName();
        return RoleMenu::on($connectionName)
            ->whereIn('role_id', $roleIds)
            ->pluck('menu_id')
            ->unique()
            ->toArray();
    }

    /**
     * 根据IDS输出菜单
     *
     * @param \app\service\admin\system\MenuService $menuService
     * @param array                              $ids
     * @param bool                               $includeButtons
     *
     * @return \Illuminate\Database\Eloquent\Collection
     * @throws \Exception
     */
    private function getMenusByIds(MenuService $menuService, array $ids, bool $includeButtons = false): Collection
    {
        if (empty($ids)) {
            return new Collection();
        }
        
        // 临时切换到管理员模式，绕过 TenantScope（它只做 WHERE tenant_id = X，会过滤掉公共菜单）
        // menu_ids 已由 getUserMenuIds() 从角色权限中间表确定，包含公共菜单和租户专属菜单
        // 此处只需按 ID 查找，不再需要 tenant_id 过滤
        TenantContext::setAdminMode(true);
        try {
            $menuModel = $menuService->dao->getModel();
            
            $chunkSize  = 200;
            $chunks     = array_chunk($ids, $chunkSize);
            $typeFilter = function ($query) use ($includeButtons) {
                $types = $includeButtons ? [1, 2, 3, 4] : [1, 2];
                $query->whereIn('type', $types);
            };

            // 分块查询 + 批量合并（减少内存占用）
            $allResults = new Collection();
            foreach ($chunks as $chunk) {
                $results    = (clone $menuModel)
                    ->whereIn('id', $chunk)
                    ->where('enabled', 1)
                    ->where($typeFilter)
                    ->orderBy('sort')
                    ->get();
                $allResults = $allResults->merge($results);
            }
            return $allResults;
        } finally {
            TenantContext::setAdminMode(false);
        }
    }

    /**
     * 获取用户角色-权限码
     *
     * @param \app\adminapi\CurrentUser $currentUser
     *
     * @return array
     */
    public function getCodesByUserRoles(CurrentUser $currentUser): array
    {
        $adminModel   = $currentUser->admin();
        if (!$adminModel) {
            return [];
        }
        
        $isSuperAdmin = boolval($adminModel->getAttribute('is_super'));
        if ($isSuperAdmin) {
            // 超级管理员使用通配符权限码，与 CurrentUser::getPermissions() 保持一致
            return ['*'];
        }

        // 普通成员 - 通过角色获取权限码
        return $currentUser->getPermissions();
    }
}
