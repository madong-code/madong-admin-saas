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

use app\enum\platform\TenantSubscriptionStatus;
use app\model\tenant\Subscription;
use app\model\tenant\Tenant;
use app\model\tenant\TenantSubscription;
use core\foundation\base\BaseDao;

/**
 * 租户-套餐订阅实例 DAO
 */
class TenantSubscriptionDao extends BaseDao
{
    protected function setModel(): string
    {
        return TenantSubscription::class;
    }

    /**
     * 获取套餐关联的租户ID列表
     */
    public function getTenantIdsBySubscriptionId(int $subscriptionId): array
    {
        return $this->getModel()::where('subscription_id', $subscriptionId)
            ->pluck('tenant_id')
            ->toArray();
    }

    /**
     * 获取租户关联的套餐ID列表
     */
    public function getSubscriptionIdsByTenantId(int $tenantId): array
    {
        return $this->getModel()::where('tenant_id', $tenantId)
            ->pluck('subscription_id')
            ->toArray();
    }

    /**
     * 同步套餐的关联租户（使用 Laravel sync 自动处理新增/删除）
     */
    public function syncTenants(int $subscriptionId, array $tenantIds): array
    {
        $subscription = Subscription::findOrFail($subscriptionId);
        $now = \Carbon\Carbon::now();
        $syncData = [];
        foreach ($tenantIds as $tid) {
            $syncData[$tid] = [
                'status'     => TenantSubscriptionStatus::ACTIVE->value,
                'start_time' => $now,
            ];
        }
        $subscription->tenants()->sync($syncData);
        return $tenantIds;
    }

    /**
     * 同步租户的关联套餐（使用 Laravel sync 自动处理新增/删除）
     */
    public function syncPlans(int $tenantId, array $planIds): void
    {
        $tenant = Tenant::findOrFail($tenantId);
        $now = \Carbon\Carbon::now();
        $syncData = [];
        foreach ($planIds as $pid) {
            $syncData[$pid] = [
                'status'     => TenantSubscriptionStatus::ACTIVE->value,
                'start_time' => $now,
            ];
        }
        $tenant->subscriptions()->sync($syncData);
    }
}
