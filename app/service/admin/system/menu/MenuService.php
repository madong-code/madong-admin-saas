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

namespace app\service\admin\system\menu;

use app\dao\system\menu\MenuDao;
use app\model\tenant\MenuTemplate;
use app\model\system\menu\Menu;
use core\foundation\base\BaseService;
use core\foundation\exception\handler\AdminException;
use core\business\tenant\context\TenantContext;
use core\business\tenant\SyncConnection;
use app\service\admin\system\role\RoleMenuService;
use madong\helper\Arr;
use madong\helper\Tree;
use support\Container;

/**
 * @method save(array $data)
 * @method selectModel(array $where, array|string $field = '*', int $page = 0, int $limit = 0, string $order = '', array $with = [], bool $search = false, ?array $withoutScopes = null)
 */
class MenuService extends BaseService
{

    public function __construct(MenuDao $dao)
    {

        $this->dao = $dao;
    }

    /**
     * 获取权限菜单树（用于套餐授权等场景）
     * 根据当前租户上下文过滤：
     * - 单租户/管理员模式：仅返回公共菜单（tenant_id IS NULL）
     * - 字段隔离：返回当前租户菜单 + 公共菜单
     * - 库隔离：切换到租户库查询（无 tenant_id 字段）
     *
     * @return array
     */
    public function getPermissionTree(): array
    {
        $query = $this->dao->getModel()
            ->where('enabled', 1);

        // 根据当前上下文添加租户过滤
        $this->applyTenantFilter($query);

        $menus = $query->orderBy('sort', 'asc')
            ->get()
            ->toArray();

        // 构建树形结构
        return $this->buildMenuTree($menus);
    }

    /**
     * 根据当前租户上下文为菜单查询添加 tenant_id 过滤
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     */
    public function applyTenantFilter($query): void
    {
        // 单租户模式：只返回公共菜单
        if (TenantContext::isSingleMode()) {
            $query->whereNull('tenant_id');
            return;
        }

        // 管理员模式（平台端）：只返回公共菜单
        if (TenantContext::isAdmin()) {
            $query->whereNull('tenant_id');
            return;
        }

        // 库隔离模式：不需要 tenant_id 过滤（切换连接即可）
        if (TenantContext::getIsolationMode() === 'database') {
            return;
        }

        // 字段隔离模式：返回当前租户的菜单 + 公共菜单
        $tenantId = TenantContext::getTenantId();
        if ($tenantId !== null) {
            $query->where(function ($q) use ($tenantId) {
                $q->whereNull('tenant_id')
                  ->orWhere('tenant_id', $tenantId);
            });
        } else {
            // 没有租户上下文时，只返回公共菜单
            $query->whereNull('tenant_id');
        }
    }

    /**
     * 输出所有权限
     *
     * @param array $menuIds
     *
     * @return array
     * @throws \Exception
     */
    public function getAllAuth(array $menuIds = []): array
    {
        // 1. 直接从数据库获取所有权限数据（带租户过滤）
        $query = $this->dao->getModel()
            ->where('path', '<>', '')
            ->where('type', '=', 4);
        $this->applyTenantFilter($query);
        $allAuthItems = $query->get(['id', 'path', 'methods'])->toArray();

        // 2. 如果传入了 $menuIds，则筛选出对应的权限项
        if (!empty($menuIds)) {
            $allAuthItems = array_filter($allAuthItems, function ($item) use ($menuIds) {
                return in_array($item['id'], $menuIds);
            });
        }

        // 3. 格式化输出（复用现有方法）
        return $this->formatAuthDataFromItems($allAuthItems);
    }

    /**
     * 根据授权项数组格式化输出为 ['METHOD' => ['/path1', '/path2'], ...] 的形式。
     *
     * @param array $allAuthItems 授权项数组，每个元素包含 'id', 'path', 'methods'
     *
     * @return array 格式化后的授权数据
     */
    private function formatAuthDataFromItems(array $allAuthItems): array
    {
        $allAuth = [];
        foreach ($allAuthItems as $item) {
            // 假设 'methods' 是逗号分隔的字符串，如 "GET,POST"
            $methodArray = explode(',', $item['methods']); // 转换为数组

            $pathKey = strtolower(trim(str_replace(' ', '', $item['path'])));

            foreach ($methodArray as $method) {
                $methodKey             = strtolower(trim($method));
                $allAuth[$methodKey][] = $pathKey;
            }
        }

        // 如果需要，可以对每个方法的路径数组进行去重
        foreach ($allAuth as &$paths) {
            $paths = array_unique($paths);
        }
        unset($paths); // 解除引用
        return $allAuth;
    }

    public function getAllMenus(array $where = [], $mode = 'menu', $isTree = true): array
    {
        // 1. 直接从数据库获取所有启用菜单（带租户过滤）
        $query = $this->dao->getModel()
            ->where('enabled', 1);
        $this->applyTenantFilter($query);
        $allMenus = $query->orderBy('sort', 'asc')->get();

        // 2. 根据 $where 条件筛选菜单数据
        $menusToProcess = Arr::filterByWhere($allMenus, $where);

        if ($mode == 'code') {
            return array_column($menusToProcess, 'code');
        }
        if (!$isTree) {
            return $menusToProcess;
        }

        // 构建树形结构
        return $this->buildMenuTree($menusToProcess);

    }

    /**
     * 构建菜单的树形结构。
     *
     * @param array $formattedMenus 已处理的菜单数据
     *
     * @return array 树形结构的菜单数据
     */
    protected function buildMenuTree(array $formattedMenus): array
    {
        $tree = new Tree($formattedMenus);
        return $tree->getTree();
    }



    /*** 重写方法***/

    /**
     * 重写更新方法：统一事务管理菜单更新、缓存清理、Casbin同步
     *
     * @param int|string $id   菜单ID
     * @param array      $data 更新数据
     *
     * @return Menu 更新后的模型
     * @throws \Throwable
     * @throws \core\exception\handler\AdminException
     */
    public function update(int|string $id, array $data): Menu
    {
        return $this->transaction(function () use ($id, $data) {
            // 1. 获取菜单模型
            $model = $this->get($id);
            if (!$model) {
                throw new AdminException("菜单ID:{$id}不存在");
            }

            // 2. 更新菜单数据（使用属性复制或直接赋值）
            $model->fill($data);
            $model->save();

            // 3. 同步Casbin策略（复用已实现的同步方法）
            $this->syncPermissionCacheAfterUpdate($id);
            return $model;
        });
    }

    /**
     * 批量删除菜单（含子菜单）并同步删除Casbin策略
     *
     * @param array $data 菜单ID数组或逗号分隔字符串
     *
     * @return array 被删除的所有菜单ID
     * @throws \Throwable
     * @throws \core\exception\handler\AdminException
     */
    public function batchDelete(array $data = []): array
    {

        return $this->transaction(function () use ($data) {
            $deletedIds = [];

            foreach ($data as $id) {
                /** @var Menu $item */
                $item = $this->get($id);
                if (!$item) {
                    continue;
                }
                // 删除菜单及子菜单，获取所有被删除的ID
                $ids        = $item->deleteWithAllChildren();
                $deletedIds = array_merge($deletedIds, $ids);
            }

            // 清理角色菜单中间表关联数据
            if (!empty($deletedIds)) {
                /** @var RoleMenuService $roleMenuService */
                $roleMenuService = Container::make(RoleMenuService::class);
                /** @var RoleMenuModel $roleMenuModel */
                $roleMenuModel = $roleMenuService->getModel();
                $roleMenuModel->whereIn('menu_id', $deletedIds)->delete();
                
                // 清理相关用户的权限缓存
                $this->clearUserPermissionCacheByMenus($deletedIds);
            }

            return array_unique($deletedIds);
        });
    }

    /**
     * 菜单更新后清理相关用户权限缓存
     *
     * @param int|string $menuId 被更新的菜单ID
     *
     * @throws \core\exception\handler\AdminException
     */
    private function syncPermissionCacheAfterUpdate(int|string $menuId): void
    {
        try {
            // 1. 查询所有关联此菜单的角色ID（通过角色菜单服务）
            /** @var RoleMenuService $roleMenuService */
            $roleMenuService = Container::make(RoleMenuService::class);
            $roleIds         = $roleMenuService->getColumn(
                ['menu_id' => $menuId],
                'role_id'
            );
            if (empty($roleIds)) {
                return; // 无关联角色，无需同步
            }

            // 2. 清理拥有这些角色的用户的权限缓存
            foreach ($roleIds as $roleId) {
                /** @var \app\service\admin\system\AdminRoleService $adminRoleService */
                $adminRoleService = Container::make(\app\service\admin\system\AdminRoleService::class);
                
                // 获取拥有此角色的用户ID列表
                $userIds = \app\model\system\AdminRole::where('role_id', $roleId)
                    ->pluck('admin_id')
                    ->toArray();
                
                if (!empty($userIds)) {
                    // 清理这些用户的权限缓存
                    $currentUser = Container::make(\app\adminapi\CurrentUser::class);
                    foreach ($userIds as $userId) {
                        $currentUser->clearCache($userId);
                    }
                }
            }
        } catch (\Throwable $e) {
            throw new AdminException($e->getMessage());
        }
    }
    
    /**
     * 清理与指定菜单相关的用户权限缓存
     *
     * @param array $menuIds 菜单ID数组
     */
    private function clearUserPermissionCacheByMenus(array $menuIds): void
    {
        try {
            if (empty($menuIds)) {
                return;
            }
            
            // 获取拥有这些菜单的角色ID
            /** @var RoleMenuService $roleMenuService */
            $roleMenuService = Container::make(RoleMenuService::class);
            /** @var RoleMenuModel $roleMenuModel */
            $roleMenuModel = $roleMenuService->getModel();
            
            $roleIds = $roleMenuModel->whereIn('menu_id', $menuIds)
                ->pluck('role_id')
                ->unique()
                ->toArray();
            
            if (!empty($roleIds)) {
                // 获取拥有这些角色的用户ID
                $userIds = \app\model\system\AdminRole::whereIn('role_id', $roleIds)
                    ->pluck('admin_id')
                    ->unique()
                    ->toArray();
                
                if (!empty($userIds)) {
                    // 清理这些用户的权限缓存
                    $currentUser = Container::make(\app\adminapi\CurrentUser::class);
                    foreach ($userIds as $userId) {
                        $currentUser->clearCache($userId);
                    }
                }
            }
        } catch (\Throwable $e) {
            // 缓存清理失败不影响主要业务，记录日志即可
            \core\logger\Logger::error("清理菜单相关用户权限缓存失败: " . $e->getMessage());
        }
    }

    // ==================== 三模菜单同步 ====================

    /**
     * 同步模板菜单到租户菜单表
     *
     * 根据当前系统模式，将 saas_template_menu 中的完整菜单树
     * 深度复制到对应的租户菜单表：
     * - FIELD 模式：复制到 sys_menu（设置 tenant_id）
     * - DB 模式：复制到租户独立库的 sys_tenant_menu
     *
     * @param int    $tenantId 租户ID
     * @param string $mode     隔离模式：field|database
     *
     * @return array 同步结果（新增菜单ID列表）
     */
    public function syncTemplateMenus(int $tenantId, string $mode = 'field'): array
    {
        // 1. 获取所有模板菜单（启用状态）
        $templates = MenuTemplate::where('enabled', 1)
            ->orderBy('sort')
            ->get()
            ->toArray();

        if (empty($templates)) {
            return [];
        }

        // 2. 建立旧ID -> 新ID 映射
        $idMap = [];
        $newMenus = [];

        // database 模式：先注册租户连接配置，确保后续 Menu::on() 可用
        if ($mode === 'database') {
            SyncConnection::register($tenantId);
        }

        // 先复制所有记录（不设置 pid）
        foreach ($templates as $item) {
            $oldId = $item['id'];
            unset($item['id'], $item['created_at'], $item['created_by'],
                  $item['updated_at'], $item['updated_by'], $item['deleted_at']);

            if ($mode === 'field') {
                $item['tenant_id'] = $tenantId;
                $menu = Menu::create($item);
            } else {
                // DB 模式：通过租户连接写入
                // 使用 TenantConnectionManager 切换连接
                $connectionName = 'tenant_' . $tenantId;
                $menu = Menu::on($connectionName)->create($item);
            }

            $idMap[$oldId] = $menu->id;
            $newMenus[$menu->id] = $menu;
        }

        // 3. 更新 parent_id
        foreach ($templates as $item) {
            $oldId = $item['id'];
            $newId = $idMap[$oldId] ?? null;
            if ($newId && !empty($item['pid']) && isset($idMap[$item['pid']])) {
                $newPid = $idMap[$item['pid']];
                $menuModel = $newMenus[$newId] ?? null;
                if ($menuModel) {
                    $menuModel->pid = $newPid;
                    $menuModel->save();
                }
            }
        }

        // 4. 更新 level 字段
        $this->rebuildMenuLevels($idMap, $templates, $mode, $tenantId);

        return [
            'tenant_id' => $tenantId,
            'mode'      => $mode,
            'count'     => count($newMenus),
            'menu_ids'  => array_keys($newMenus),
        ];
    }

    /**
     * 将套餐授权的权限菜单同步到指定租户（按 permissionIds 过滤）
     *
     * @param int    $tenantId      租户ID
     * @param array  $permissionIds 套餐授权的 sys_menu 菜单ID列表
     * @param string $mode          隔离模式：field|database
     *
     * @return array
     */
    public function syncPermissionToTenant(int $tenantId, array $permissionIds, string $mode = 'field'): array
    {
        if (empty($permissionIds)) {
            return ['tenant_id' => $tenantId, 'mode' => $mode, 'count' => 0];
        }

        // 1. 从 saas_template_menu 获取模板菜单（permissionIds 是模板ID）
        $allEnabled = MenuTemplate::where('enabled', 1)
            ->orderBy('sort', 'asc')
            ->get()
            ->toArray();

        // 收集所有需要的 ID（包含父级链，确保树完整）
        $neededIds = $this->collectParentIds($allEnabled, $permissionIds);
        $templates = array_values(array_filter($allEnabled, fn($m) => in_array($m['id'], $neededIds)));

        if (empty($templates)) {
            return ['tenant_id' => $tenantId, 'mode' => $mode, 'count' => 0];
        }

        // database 模式：先注册租户连接配置，确保后续 Menu::on() 可用
        if ($mode === 'database') {
            SyncConnection::register($tenantId);
        }

        // 2. 清除租户现有菜单
        if ($mode === 'field') {
            Menu::where('tenant_id', $tenantId)->delete();
        } else {
            $connectionName = 'tenant_' . $tenantId;
            Menu::on($connectionName)->truncate();
        }

        // 3. 建立 ID 映射并复制
        $idMap = [];
        $newMenus = [];
        foreach ($templates as $item) {
            $oldId = $item['id'];
            unset($item['id'], $item['created_at'], $item['created_by'],
                  $item['updated_at'], $item['updated_by'], $item['deleted_at']);

            if ($mode === 'field') {
                $item['tenant_id'] = $tenantId;
                $menu = Menu::create($item);
            } else {
                $connectionName = 'tenant_' . $tenantId;
                $menu = Menu::on($connectionName)->create($item);
            }

            $idMap[$oldId] = $menu->id;
            $newMenus[$menu->id] = $menu;
        }

        // 4. 更新 parent_id
        foreach ($templates as $item) {
            $oldId = $item['id'];
            $newId = $idMap[$oldId] ?? null;
            if ($newId && !empty($item['pid']) && isset($idMap[$item['pid']])) {
                $newPid = $idMap[$item['pid']];
                $menuModel = $newMenus[$newId] ?? null;
                if ($menuModel) {
                    $menuModel->pid = $newPid;
                    $menuModel->save();
                }
            }
        }

        // 5. 更新 level 字段
        $this->rebuildMenuLevels($idMap, $templates, $mode, $tenantId);

        return [
            'tenant_id' => $tenantId,
            'mode'      => $mode,
            'count'     => count($newMenus),
            'menu_ids'  => array_keys($newMenus),
        ];
    }

    /**
     * 收集所有需要的父级 ID
     */
    private function collectParentIds(array $allMenus, array $targetIds): array
    {
        $result = [];
        $idMap = [];
        foreach ($allMenus as $m) {
            $idMap[$m['id']] = $m;
        }

        $walk = function (int $id) use (&$walk, $idMap, &$result) {
            if (in_array($id, $result)) return;
            $result[] = $id;
            if (isset($idMap[$id]) && !empty($idMap[$id]['pid'])) {
                $walk((int)$idMap[$id]['pid']);
            }
        };

        foreach ($targetIds as $id) {
            $walk((int)$id);
        }

        return $result;
    }

    /**
     * 重建菜单 level 字段
     */
    private function rebuildMenuLevels(array $idMap, array $templates, string $mode, int $tenantId): void
    {
        $levelMap = [];
        foreach ($templates as $item) {
            $newId = $idMap[$item['id']] ?? null;
            if (!$newId) continue;

            // 构建 level（父ID集合）
            $levels = [];
            $parentId = $item['pid'];
            while ($parentId && isset($idMap[$parentId])) {
                array_unshift($levels, $idMap[$parentId]);
                $parentTemplate = current(array_filter($templates, fn($t) => $t['id'] == $parentId));
                $parentId = $parentTemplate ? ($parentTemplate['pid'] ?? 0) : 0;
            }
            $levelMap[$newId] = !empty($levels) ? implode(',', $levels) : null;
        }

        foreach ($levelMap as $menuId => $level) {
            if ($mode === 'field') {
                Menu::where('id', $menuId)
                    ->where('tenant_id', $tenantId)
                    ->update(['level' => $level]);
            } else {
                Menu::on('tenant_' . $tenantId)
                    ->where('id', $menuId)
                    ->update(['level' => $level]);
            }
        }
    }
}
