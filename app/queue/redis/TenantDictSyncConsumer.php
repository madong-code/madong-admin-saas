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

use app\model\sync\SyncDict;
use app\model\tenant\Tenant;
use core\foundation\base\BaseQueueConsumer;
use core\business\tenant\SyncConnection;
use support\Db;
use core\infrastructure\logger\Logger;

/**
 * 租户字典同步消费者
 * 处理 database 模式租户的字典变更（创建/更新/删除）
 */
class TenantDictSyncConsumer extends BaseQueueConsumer
{
    public string $queue = 'tenant-dict-sync';
    protected int $maxRetry = 3;

    protected function handle(array $data): void
    {
        $action = $data['action'] ?? '';
        $tenantIds = $data['tenant_ids'] ?? [];

        if (empty($tenantIds)) {
            Logger::warning('[TenantDictSync] 无目标租户');
            return;
        }

        foreach ($tenantIds as $tenantId) {
            if (!$this->checkDatabaseExists($tenantId)) {
                continue;
            }

            $connectionName = 'tenant_' . $tenantId;

            switch ($action) {
                case 'create':
                    $this->doCreate($connectionName, $data);
                    break;
                case 'update':
                    $this->doUpdate($connectionName, $data);
                    break;
                case 'delete':
                    $this->doDelete($connectionName, $data);
                    break;
                default:
                    Logger::warning('[TenantDictSync] 未知操作', ['action' => $action]);
            }
        }
    }

    private function doCreate(string $connectionName, array $data): void
    {
        $templateData = $data['data'] ?? [];
        if (empty($templateData)) {
            return;
        }

        $templateId = $templateData['id'] ?? 0;
        if (empty($templateId)) {
            return;
        }

        // 避免重复插入
        $exists = SyncDict::on($connectionName)->where('template_id', $templateId)->exists();
        if ($exists) {
            return;
        }

        $insert = [
            'template_id' => $templateId,
            'tenant_id'   => 0,
            'group_code'  => $templateData['group_code'] ?? null,
            'name'        => $templateData['name'] ?? '',
            'code'        => $templateData['code'] ?? '',
            'sort'        => $templateData['sort'] ?? 0,
            'data_type'   => $templateData['data_type'] ?? 1,
            'description' => $templateData['description'] ?? null,
            'enabled'     => $templateData['enabled'] ?? 1,
            'created_at'  => $templateData['created_at'] ?? null,
            'created_by'  => $templateData['created_by'] ?? 0,
            'updated_at'  => $templateData['updated_at'] ?? null,
            'updated_by'  => $templateData['updated_by'] ?? 0,
        ];

        SyncDict::on($connectionName)->insert($insert);
    }

    private function doUpdate(string $connectionName, array $data): void
    {
        $templateIds = $data['template_ids'] ?? [];
        $updateData = $data['data'] ?? [];
        if (empty($templateIds) || empty($updateData)) {
            return;
        }

        SyncDict::on($connectionName)
            ->whereIn('template_id', $templateIds)
            ->update($updateData);
    }

    private function doDelete(string $connectionName, array $data): void
    {
        $templateIds = $data['template_ids'] ?? [];
        if (empty($templateIds)) {
            return;
        }

        SyncDict::on($connectionName)
            ->whereIn('template_id', $templateIds)
            ->delete();
    }

    private function checkDatabaseExists(int|string $tenantId): bool
    {
        try {
            $tenant = Tenant::find($tenantId);
            if (!$tenant) {
                return false;
            }

            SyncConnection::register($tenantId);
            Db::connection('tenant_' . $tenantId)->getPdo();
            return true;
        } catch (\Throwable $e) {
            Logger::warning('[TenantDictSync] 租户数据库不可用', [
                'tenant_id' => $tenantId,
                'error'     => $e->getMessage(),
            ]);
            return false;
        }
    }
}
