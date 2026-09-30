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
namespace app\service\platform\subscription;

use app\dao\tenant\SubscriptionDao;
use app\dao\tenant\SubscriptionPermissionDao;
use app\dao\tenant\TenantSubscriptionDao;
use core\foundation\base\BaseService;
use support\Container;

class SubscriptionService extends BaseService
{
    public function __construct(SubscriptionDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 授权套餐：同步权限策略到 subscription_permission 表（主库）
     * 注：仅记录策略，不直接同步到租户菜单。
     * 租户菜单的同步在套餐关联租户时由 TenantSyncService 处理。
     */
    public function authorize(int|string $id, array $permissionIds): array
    {
        $model = $this->dao->get($id);
        if (!$model) throw new \RuntimeException('套餐不存在');

        // 1. 同步到 subscription_permission 表
        if (!empty($permissionIds)) {
            /** @var SubscriptionPermissionDao $permDao */
            $permDao = Container::make(SubscriptionPermissionDao::class);
            $permDao->syncPermissions((int)$id, $permissionIds);
        }

        return $permissionIds;
    }

    /**
     * 关联租户（批量创建订阅实例）
     */
    public function bindTenant(int|string $id, array $tenantIds): array
    {
        $model = $this->dao->get($id);
        if (!$model) throw new \RuntimeException('套餐不存在');

        /** @var TenantSubscriptionDao $tsDao */
        $tsDao = Container::make(TenantSubscriptionDao::class);
        return $tsDao->syncTenants((int)$id, $tenantIds);
    }
}
