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
namespace app\dao\tenant;

use app\model\tenant\SubscriptionPermission;
use core\foundation\base\BaseDao;

/**
 * 套餐权限策略 DAO
 */
class SubscriptionPermissionDao extends BaseDao
{
    protected function setModel(): string
    {
        return SubscriptionPermission::class;
    }

    /**
     * 获取套餐关联的权限ID列表
     */
    public function getPermissionIdsBySubscriptionId(int $subscriptionId): array
    {
        return $this->getModel()::where('subscription_id', $subscriptionId)
            ->pluck('permission_id')
            ->toArray();
    }

    /**
     * 同步套餐权限（先删后增，permissionIds为权限ID数组）
     */
    public function syncPermissions(int|string $subscriptionId, array $permissionIds): void
    {
        $this->getModel()::where('subscription_id', $subscriptionId)->delete();

        if (!empty($permissionIds)) {
            $data = array_map(fn($permissionId) => [
                'subscription_id' => $subscriptionId,
                'permission_id'   => $permissionId,
            ], $permissionIds);

            $this->getModel()::insert($data);
        }
    }
}
