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
 * Official Website: https://madong.tech
 */

namespace app\service\admin\system\admin;

use app\enum\system\PolicyPrefix;
use core\foundation\base\BaseService;
use core\casbin\Permission;
use core\foundation\exception\handler\AdminException;

class CasbinService extends BaseService
{

    public function __construct()
    {
        $this->dao = null;
    }

    /**
     * 更新casbin 策略
     *
     * @param array $currentPermissions
     * @param array $incomingPolicies
     *
     * @throws \core\exception\handler\AdminException
     */
    public function updateCasbinPolicies(array $currentPermissions, array $incomingPolicies): void
    {
        // 计算需要删除的权限：当前有但传入没有的
        $permissionsToDelete = array_filter($currentPermissions, function ($currentPolicy) use ($incomingPolicies) {
            foreach ($incomingPolicies as $incomingPolicy) {
                if (
                    (string)$currentPolicy[0] === (string)$incomingPolicy[0] &&
                    (string)$currentPolicy[1] === (string)$incomingPolicy[1] &&
                    (string)$currentPolicy[2] === (string)$incomingPolicy[2] &&
                    (string)$currentPolicy[5] === (string)$incomingPolicy[5]
                ) {
                    return false; // 找到了匹配的，当前权限不需要删除
                }
            }
            return true; // 没有找到匹配的，当前权限需要删除
        });

        // 计算需要添加的权限：传入有但当前没有的
        $permissionsToAdd = array_filter($incomingPolicies, function ($incomingPolicy) use ($currentPermissions) {
            foreach ($currentPermissions as $currentPolicy) {
                if (
                    (string)$incomingPolicy[0] === (string)$currentPolicy[0] &&
                    (string)$incomingPolicy[1] === (string)$currentPolicy[1] &&
                    (string)$incomingPolicy[2] === (string)$currentPolicy[2] &&
                    (string)$incomingPolicy[5] === (string)$currentPolicy[5]
                ) {
                    return false; // 找到了匹配的，传入权限不需要添加
                }
            }
            return true; // 没有找到匹配的，传入权限需要添加
        });

        // 删除不需要的权限
        foreach ($permissionsToDelete as $policy) {
            Permission::removeFilteredPolicy("0", $policy[0], $policy[1], '', '', '', $policy[5]);
        }

        // 添加新的权限
        foreach ($permissionsToAdd as $policy) {
            $res = Permission::addPolicy(...$policy);
            if (!$res) {
                throw new AdminException('添加失败：' . $policy[5]);
            }
        }
    }

    /**
     * 根据菜单ID删除关联Casbin策略
     *
     * @param int|string $menuId 菜单ID
     *
     * @throws AdminException
     */
    public function deletePoliciesByMenuId(int|string $menuId): void
    {
        try {
            $policyIdentifier = PolicyPrefix::MENU->value . (string)$menuId;
            Permission::removeFilteredPolicy("0", '', '', '', '', '', $policyIdentifier);
        } catch (\Throwable $e) {
            throw new AdminException("删除菜单关联策略失败：{$e->getMessage()}");
        }
    }

}
