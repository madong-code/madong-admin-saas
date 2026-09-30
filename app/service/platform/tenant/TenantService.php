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
namespace app\service\platform\tenant;

use app\dao\tenant\TenantDao;
use app\dao\tenant\TenantSubscriptionDao;
use app\model\system\admin\Admin;
use core\foundation\base\BaseService;
use core\business\tenant\SyncConnection;
use support\Container;

class TenantService extends BaseService
{
    public function __construct(TenantDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 同步租户数据（委托给 TenantSyncService）
     */
    public function syncData(int|string $id): array
    {
        $tenant = $this->dao->get($id);
        if (!$tenant) throw new \RuntimeException('租户不存在');

        /** @var TenantSyncService $syncService */
        $syncService = Container::make(TenantSyncService::class);
        return $syncService->syncData((int)$id, $tenant->database_mode ?? 'field');
    }

    /**
     * 授权套餐：为租户绑定多个套餐（通过中间表多对多）
     */
    public function bindPlan(int|string $id, array $planIds): array
    {
        $model = $this->dao->get($id);
        if (!$model) throw new \RuntimeException('租户不存在');

        /** @var TenantSubscriptionDao $tsDao */
        $tsDao = Container::make(TenantSubscriptionDao::class);
        $tsDao->syncPlans((int)$id, $planIds);

        return [
            'tenant_id' => (int)$id,
            'plan_ids'  => $planIds,
        ];
    }

    /**
     * 创建管理员并关联到租户
     *
     * - field 模式：写入主库 sys_admin，带上 tenant_id
     * - database 模式：写入租户独立库的 sys_admin
     */
    public function createAdminAndLink(int $tenantId, string $account, string $password, string $mode = 'field'): void
    {
        $connection = SyncConnection::getConnectionName($tenantId, $mode);

        $this->transaction(function () use ($connection, $tenantId, $account, $password, $mode) {
            $query = Admin::on($connection)->where('user_name', $account);
            if ($mode === 'field') {
                $query->where('tenant_id', $tenantId);
            }
            $exists = $query->exists();
            if ($exists) throw new \RuntimeException('管理员账号已存在');

            $data = [
                'user_name' => $account,
                'real_name' => $account,
                'password'  => $this->passwordHash($password),
                'enabled'   => 1,
                'is_super'  => 1,
            ];
            if ($mode === 'field') {
                $data['tenant_id'] = $tenantId;
            }

            Admin::on($connection)->create($data);
        }, true, $connection);
    }

}
