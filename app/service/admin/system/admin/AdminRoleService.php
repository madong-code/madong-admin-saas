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

use app\dao\system\admin\AdminRoleDao;
use core\foundation\base\BaseService;
use core\foundation\exception\handler\AdminException;
use support\Container;

/**
 * @author Mr.April
 * @since  1.0
 * @method query()
 */
class AdminRoleService extends BaseService
{

    public function __construct(AdminRoleDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 保存用户角色关联
     *
     * @param array $data 包含user_id和role_id_list的数组
     *
     * @throws AdminException
     */
    public function save(array $data): void
    {
        try {
            $this->transaction(function () use ($data) {
                $userId     = $data['id'] ?? 0;
                $newRoleIds = $data['role_id_list'] ?? [];

                if ($userId <= 0) {
                    throw new AdminException("用户ID不能为空");
                }

                // 1. 获取用户现有角色ID
                $oldRoleIds = array_column(
                    $this->query()->where('admin_id', $userId)->get()->toArray(),
                    'role_id'
                );

                // 2. 计算需要删除和新增的角色ID（差集计算）
                $rolesToDelete = array_diff($oldRoleIds, $newRoleIds);
                $rolesToAdd    = array_diff($newRoleIds, $oldRoleIds);

                // 3. 批量清理旧角色关联
                if (!empty($rolesToDelete)) {
                    // 中间表批量删除
                    $this->dao->getModel()
                        ->where('admin_id', $userId)
                        ->whereIn('role_id', $rolesToDelete)
                        ->delete();
                }

                // 4. 批量添加新角色关联
                if (!empty($rolesToAdd)) {
                    // 中间表批量插入
                    $insertData = array_map(function ($roleId) use ($userId) {
                        return ['admin_id' => $userId, 'role_id' => $roleId];
                    }, $rolesToAdd);
                    $this->dao->getModel()->insert($insertData);
                }
                
                // 5. 清理用户权限缓存
                $currentUser = Container::make(\app\adminapi\CurrentUser::class);
                $currentUser->clearCache($userId);
            });
        } catch (\Throwable $e) {
            throw new AdminException("角色授权失败：{$e->getMessage()}");
        }
    }

    /**
     * 移除用户-关联角色
     *
     * @param array $data
     *
     * @throws \core\exception\handler\AdminException
     */
    public function removeUserRole(array $data)
    {
        try {
            $this->transaction(function () use ($data) {
                $userIds = [];
                foreach ($data as $item) {
                    $this->dao->getModel()->where($item)->delete();
                    $userIds[] = $item['admin_id'];
                }
                
                // 清理相关用户的权限缓存
                $userIds = array_unique($userIds);
                if (!empty($userIds)) {
                    $currentUser = Container::make(\app\adminapi\CurrentUser::class);
                    foreach ($userIds as $userId) {
                        $currentUser->clearCache($userId);
                    }
                }
            });
        } catch (\Throwable $e) {
            throw new AdminException($e->getMessage());
        }
    }

    /**
     * 保存用户-关联角色
     *
     * @param array $data
     *
     * @return void
     * @throws \core\exception\handler\AdminException
     */
    public function saveUserRoles(array $data): void
    {
        try {
            $this->transaction(function () use ($data) {
                $this->dao->saveAll($data);
                
                // 清理相关用户的权限缓存
                $userIds = array_unique(array_column($data, 'admin_id'));
                if (!empty($userIds)) {
                    $currentUser = Container::make(\app\adminapi\CurrentUser::class);
                    foreach ($userIds as $userId) {
                        $currentUser->clearCache($userId);
                    }
                }
            });
        } catch (\Throwable $e) {
            throw new AdminException($e->getMessage());
        }
    }

    /**
     * 通过角色ID获取用户ID列表
     *
     * @param int|string $roleId
     * @return array
     */
    public function getUserIdsByRoleId(int|string $roleId): array
    {
        return $this->dao->getModel()
            ->where('role_id', $roleId)
            ->pluck('admin_id')
            ->toArray();
    }

}
