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
namespace app\queue\redis;

use app\model\sync\SyncMenu;
use app\model\tenant\Tenant;
use core\foundation\base\BaseQueueConsumer;
use core\business\tenant\SyncConnection;
use support\Db;
use core\infrastructure\logger\Logger;

/**
 * 租户菜单同步消费者
 * 处理 database 模式租户的菜单变更（更新/删除）
 */
class TenantMenuSyncConsumer extends BaseQueueConsumer
{
    public string $queue = 'tenant-menu-sync';
    protected int $maxRetry = 3;

    protected function handle(array $data): void
    {
        $action = $data['action'] ?? '';
        $tenantIds = $data['tenant_ids'] ?? [];

        if (empty($tenantIds)) {
            Logger::warning('[TenantMenuSync] 无目标租户');
            return;
        }

        foreach ($tenantIds as $tenantId) {
            if (!$this->checkDatabaseExists($tenantId)) {
                continue;
            }

            $connectionName = 'tenant_' . $tenantId;

            switch ($action) {
                case 'update':
                    $this->doUpdate($connectionName, $data);
                    break;
                case 'delete':
                    $this->doDelete($connectionName, $data);
                    break;
                default:
                    Logger::warning('[TenantMenuSync] 未知操作', ['action' => $action]);
            }
        }
    }

    private function doUpdate(string $connectionName, array $data): void
    {
        $templateId = $data['template_id'] ?? 0;
        $updateData = $data['data'] ?? [];
        if (empty($updateData)) return;

        // 白名单过滤：入参为原始请求数组，且 Builder::update() 不过滤 fillable，
        // 需显式排除主键/树形结构与审计字段，避免把 id 等写入 SQL。
        $writableFields = array_diff(
            (new SyncMenu())->getFillable(),
            ['id', 'pid', 'level', 'template_id', 'tenant_id', 'created_at', 'created_by', 'updated_at', 'updated_by', 'deleted_at']
        );
        $updateData = array_intersect_key($updateData, array_flip($writableFields));
        if (empty($updateData)) return;

        SyncMenu::on($connectionName)
            ->where('template_id', $templateId)
            ->update($updateData);
    }

    private function doDelete(string $connectionName, array $data): void
    {
        $templateIds = $data['template_ids'] ?? [];
        if (empty($templateIds)) return;

        SyncMenu::on($connectionName)
            ->whereIn('template_id', $templateIds)
            ->delete();
    }

    private function checkDatabaseExists(int|string $tenantId): bool
    {
        try {
            $tenant = Tenant::find($tenantId);
            if (!$tenant) return false;

            SyncConnection::register($tenantId);
            Db::connection('tenant_' . $tenantId)->getPdo();
            return true;
        } catch (\Throwable $e) {
            Logger::warning('[TenantMenuSync] 租户数据库不可用', [
                'tenant_id' => $tenantId,
                'error'     => $e->getMessage(),
            ]);
            return false;
        }
    }
}
