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

namespace app\service\admin\system\role;

use app\dao\system\role\RoleMenuDao;
use core\foundation\base\BaseService;
use core\foundation\exception\handler\AdminException;
use support\Container;

/**
 * @method getColumn(int[]|string[] $array, string $string)
 */
class RoleMenuService extends BaseService
{

    public function __construct(RoleMenuDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 更新设置角色权限
     *
     * @param $data
     *
     * @throws \core\exception\handler\AdminException
     */
//    public function save($data): void
//    {
//        try {
//            $this->transaction(function () use ($data) {
//                $roleId         = $data['role_id'] ?? '';
//                $newPermissions = $data['menu_id'] ?? [];
//                if (empty($roleId)) {
//                    throw new AdminException('参数错误缺少role_id', -1);
//                }
//
//                // 1. 获取角色模型（确保角色存在）
//                /** @var SysRoleService $roleService */
//                $roleService = Container::make(SysRoleService::class);
//                $roleModel   = $roleService->get($roleId);
//                if (!$roleModel) {
//                    throw new AdminException('角色不存在', -1);
//                }
//
//                // 2. 同步 role_menu 关联关系（自动处理新增/删除，无需手动计算差异）
//                // 假设 SysRole 模型定义了 roleMenus 关联（hasMany: SysRoleMenu）
//                $roleModel->menus()->sync($newPermissions);
//
//                // 3. 获取同步后的菜单ID（从关联关系中直接获取，确保数据一致性）
//                $syncedMenuIds = $roleModel->menus()->pluck('menu_id')->toArray();
//
//                // 4. 构建 Casbin 策略（基于同步后的最新菜单权限）
//                $casbinRoleId = PolicyPrefix::ROLE->value . $roleId;
//                $domain       = '*';
//
//                // 4.1 获取菜单详情（用于构建策略）
//                /** @var SysMenuService $menuService */
//                $menuService = Container::make(SysMenuService::class);
//                $menuDetails = $menuService->selectList(['id' => $syncedMenuIds], '*', 0, 0, 'sort', [], true)->toArray();
//
//                $incomingPolicies = [];
//                foreach ($menuDetails as $menu) {
//                    if (empty($menu['code'])) continue;
//                    $incomingPolicies[] = [
//                        $casbinRoleId,                      // sub (角色标识)
//                        $domain,                            // dom (租户域)
//                        $menu['code'],                      // obj (权限码)
//                        '*',                                // act (操作类型)
//                        $menu['methods'],                   // method (HTTP方法)
//                        PolicyPrefix::MENU->value . $menu['id'], // trace_id
//                    ];
//                }
//
//                // 4.2 更新 Casbin 策略
//                $currentCasbinPolicies = Permission::getImplicitResourcesForUser($casbinRoleId, $domain);
//                /** @var SysCasbinService $casbinService */
//                $casbinService = Container::make(SysCasbinService::class);
//                $casbinService->updateCasbinPolicies($currentCasbinPolicies, $incomingPolicies);
//
//            });
//        } catch (\Throwable $e) {
//            throw new AdminException($e->getMessage());
//        }
//    }

    /**
     * 更新设置角色权限（含事务封装）
     *
     * @param $data
     *
     * @throws \core\exception\handler\AdminException
     */
    public function save($data): void
    {
        try {
            $this->transaction(function () use ($data) {
                $this->saveWithoutTransaction($data);
            });
        } catch (\Throwable $e) {
            throw new AdminException($e->getMessage());
        }
    }

    /**
     * 无事务版本：核心业务逻辑（供内部/外部事务复用）
     *
     * @param array $data
     *
     * @throws \core\exception\handler\AdminException
     */
    public function saveWithoutTransaction(array $data): void
    {
        try {
            $roleId         = $data['role_id'] ?? '';
            $newPermissions = $data['menu_id'] ?? [];
            if (empty($roleId)) {
                throw new AdminException('参数错误缺少role_id');
            }
            // 1. 获取角色模型（确保角色存在）
            /** @var RoleService $roleService */
            $roleService = Container::make(RoleService::class);
            $roleModel   = $roleService->get($roleId);
            if (!$roleModel) {
                throw new AdminException('角色不存在');
            }

            // 2. 同步 role_menu 关联关系（自动处理新增/删除，无需手动计算差异）
            $roleModel->menus()->sync($newPermissions);

            // 3. 清理相关用户的权限缓存
            $this->clearUserPermissionCacheByRole($roleId);
            
        } catch (\Throwable $e) {
            throw new AdminException($e->getMessage());
        }
    }
    
    /**
     * 清理拥有此角色的用户的权限缓存
     *
     * @param string|int $roleId 角色ID
     */
    private function clearUserPermissionCacheByRole(string|int $roleId): void
    {
        try {
            // 获取拥有此角色的所有用户
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
        } catch (\Throwable $e) {
            // 缓存清理失败不影响主要业务，记录日志即可
            \core\infrastructure\logger\Logger::error("清理角色{$roleId}的用户权限缓存失败: " . $e->getMessage());
        }
    }

}
