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

use app\dao\system\role\RoleDao;
use app\model\system\role\Role;
use core\foundation\base\BaseService;
use core\foundation\exception\handler\AdminException;
use madong\helper\Arr;
use madong\helper\PropertyCopier;
use support\Container;

class RoleService extends BaseService
{

    /**
     * 缓存key
     */
    const CACHE_ALL_ROLES_DATA = 'roles_all_data';

    public function __construct(RoleDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 获取所有的角色
     *
     * @param array $where
     *
     * @return array
     * @throws \Exception
     */
    public function getAllRoles(array $where = []): array
    {
        // 1. 优先从缓存获取所有角色数据
        $allRoles = $this->cacheDriver()->remember(
            self::CACHE_ALL_ROLES_DATA, // 缓存键名
            function () {
                $results = $this->dao->getModel()
                    ->where('enabled', 1)
                    ->orderBy('sort', 'asc')
                    ->get(["id",
                           "pid",
                           "name",
                           "code",
                           "is_super_admin",
                           "role_type",
                           "data_scope",
                           "enabled",
                           "sort"]);

                return $results->toArray();
            }
        );
        // 2.$where 条件筛数据
        return Arr::filterByWhere($allRoles, $where);
    }

    /**
     * 传入角色id获取权限
     *
     * @param array  $where
     * @param string $mode merge|unique
     *
     * @return array
     * @throws \Exception
     */
    public function getPermissions(array $where = [], string $mode = 'merge'): array
    {
        // 1. 优先从缓存获取所有全量角色数据
        $data = $this->cacheDriver()->remember(
            self::CACHE_ALL_ROLES_DATA, // 缓存键名
            function () {
                return $this->dao->getRolePermissions();
            }
        );
        //2. 条件过滤
        $result = Arr::filterByWhere($data, $where);
        if ($mode == 'unique') {
            return $result['menu'] ?? [];
        }
        return [
            'menu'   => array_merge([], ...array_column($result, 'menu')),
            'api'    => array_merge([], ...array_column($result, 'api')),
            'button' => array_merge([], ...array_column($result, 'button')),
        ];
    }

    /**
     * 通过角色id 获取菜单权限IDS
     *
     * @param int|string  $id
     * @param string|null $tenantId
     *
     * @return array
     */
    public function getPermissionColumns(int|string $id, string|null $tenantId = null): array
    {
        $role = $this->dao->get($id);
        if (!$role) {
            return [];
        }
        
        // 通过关联关系获取菜单ID
        return $role->menus()->pluck('id')->toArray();
    }

    /**
     * save
     *
     * @param array $data
     *
     * @return Role|null
     * @throws \core\exception\handler\AdminException
     */
    public function save(array $data): Role|null
    {
        try {
            return $this->transaction(function () use ($data) {
                $model = $this->dao->save($data);
                $handlePermissions = isset($data['permissions']) && $data['permissions'] !== null;
                
                // 有传权限key，才处理权限
                if ($handlePermissions) {
                    $menus = $data['permissions'];
                    // 同步角色菜单关联
                    $model->menus()->sync($menus);
                    
                    // 清理相关用户的权限缓存
                    $this->clearUserPermissionCacheByRole($model->id);
                }
                return $model;
            });
        } catch (\Throwable $e) {
            throw new AdminException($e->getMessage());
        }
    }

    /**
     * 编辑
     *
     * @param $id
     * @param $data
     *
     * @return void
     * @throws \core\exception\handler\AdminException
     */
//    public function update($id, $data): void
//    {
//        try {
//            $this->transaction(function () use ($id, $data) {
//                // 获取传入的权限列表（如果存在）
//                $menus = $data['permissions'] ?? [];
//                // 从数据访问对象中获取模型实例
//                $model = $this->dao->get($id);
//                if (!$model) {
//                    return;
//                }
//                // 使用属性复制器将传入的数据复制到模型中
//                PropertyCopier::copyProperties((object)$data, $model);
//                $model->save();
//
//                // 格式化用户标识符，用于 Casbin
//                $roleId = PolicyPrefix::ROLE->value . strval($model->id);
//                $domain = '*';
//
//                // 获取当前用户在 Casbin 中的隐式资源权限
//                $currentPolicies = Permission::getImplicitResourcesForUser($roleId, $domain);
//
//                // 提取完整的当前权限（0-5）
//                $currentPermissions = array_map(
//                    function ($policy) {
//                        return $policy; // 保留完整的策略数组
//                    },
//                    $currentPolicies
//                );
//
//                // 将传入的菜单数据转换为 Casbin 策略格式
//                $menuService   = new SysMenuService();
//                $policyService = new SysCasbinService();
//
//                $menusData = $menuService->getAllMenus(['id' => $menus], 'menu', false);
//                // 构建传入的权限列表，格式与当前权限相同
//                $incomingPolicies = [];
//                foreach ($menusData as $item) {
//                    $path = $policyService->resolvePermissionIdentifier($item);
//                    // 构建策略数组
//                    $policy = [
//                        $roleId,                                 // sub
//                        $domain,                                 // dom
//                        $path,                                   // obj
//                        '*',                                     // act（根据你的业务需求调整）
//                        $item['methods'],                        // method
//                        PolicyPrefix::MENU->value . $item['id'], // trace_id 资源归属用于追踪
//                    ];
//                    // 保留完整的策略数组
//                    $incomingPolicies[] = $policy;
//                }
//                // 更新 Casbin 权限
//                $policyService->updateCasbinPolicies($currentPermissions, $incomingPolicies);
//                // 同步 Casbin 权限，确保只有授权角色保留
//                $model->casbin()->sync([$roleId]);
//            });
//        } catch (\Throwable $e) {
//            // 捕获异常并抛出自定义异常
//            throw new AdminException($e->getMessage());
//        }

    /**
     * 编辑
     *
     * @param $id
     * @param $data
     *
     * @return void
     * @throws \core\exception\handler\AdminException
     */
    public function update($id, $data): void
    {
        try {
            $this->transaction(function () use ($id, $data) {
                $model = $this->dao->get($id);
                if (!$model) {
                    return;
                }
                
                PropertyCopier::copyProperties((object)$data, $model);
                $model->save();

                // 检查 permissions 是否存在且不为 null（未定义或显式 null 时不处理权限）
                $handlePermissions = isset($data['permissions']) && $data['permissions'] !== null;
                if ($handlePermissions) {
                    $menus = $data['permissions']; // 获取权限列表
                    
                    // 同步角色菜单关联
                    $model->menus()->sync($menus);
                    
                    // 清理相关用户的权限缓存
                    $this->clearUserPermissionCacheByRole($model->id);
                }
            });
        } catch (\Throwable $e) {
            // 捕获异常并抛出自定义异常
            throw new AdminException($e->getMessage());
        }
    }

    /**
     * 删除角色
     *
     * @param array $id
     * @param bool  $force
     *
     * @return mixed
     * @throws \core\exception\handler\AdminException
     */
    public function destroy(array $id, bool $force = false): mixed
    {
        try {
            return $this->transaction(function () use ($id, $force) {
                $data       = Arr::normalize($id);
                $deletedIds = [];
                
                foreach ($data as $id) {
                    $item = $this->get($id);
                    if ($item) {
                        $primaryKey = $item->getPk();
                        
                        // 清理相关用户的权限缓存
                        $this->clearUserPermissionCacheByRole($id);
                        
                        $item->delete();
                        $deletedIds[] = $item->{$primaryKey};
                    }
                }
                return $deletedIds;
            });
        } catch (\Throwable $e) {
            throw new AdminException($e->getMessage());
        }
    }

    /**
     * 分配数据权限
     *
     * @param $id
     * @param $data
     *
     * @throws \core\exception\handler\AdminException
     */
    public function updateScope($id, $data): void
    {
        try {
            $this->transaction(function () use ($id, $data) {
                $scope = $data['scopes'] ?? [];
                $model = $this->dao->get($id);
                PropertyCopier::copyProperties((object)$data, $model);
                if (!empty($model)) {
                    $model->save();
                    if ($model->data_scope !== 2) {
                        $scope = [];
                    }
                    $model->scopes()->sync($scope);
                }
                $this->cacheDriver()->delete(self::CACHE_ALL_ROLES_DATA);
            });
        } catch (\Throwable $e) {
            throw new AdminException($e->getMessage());
        }
    }

    /**
     * 清理角色的用户权限缓存
     *
     * @param int|string $roleId
     */
    private function clearUserPermissionCacheByRole(int|string $roleId): void
    {
        // 获取拥有此角色的所有用户
        /** @var AdminRoleService $adminRoleService */
        $adminRoleService = Container::make(AdminRoleService::class);
        $userIds = $adminRoleService->getUserIdsByRoleId($roleId);
        
        if (!empty($userIds)) {
            // 清理每个用户的权限缓存
            foreach ($userIds as $userId) {
                $cacheKey = 'user_permissions_' . $userId;
                $this->cacheDriver()->delete($cacheKey);
            }
        }
    }

}
