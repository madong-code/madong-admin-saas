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
use app\model\sync\SyncDictItem;
use app\model\tenant\Tenant;
use core\foundation\base\BaseQueueConsumer;
use core\business\tenant\SyncConnection;
use support\Db;
use core\infrastructure\logger\Logger;

/**
 * 租户字典项同步消费者
 * 处理 database 模式租户的字典项变更（创建/更新/删除）
 */
class TenantDictItemSyncConsumer extends BaseQueueConsumer
{
    public string $queue = 'tenant-dict-item-sync';
    protected int $maxRetry = 3;

    protected function handle(array $data): void
    {
        $action = $data['action'] ?? '';
        $tenantIds = $data['tenant_ids'] ?? [];

        if (empty($tenantIds)) {
            Logger::warning('[TenantDictItemSync] 无目标租户');
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
                    Logger::warning('[TenantDictItemSync] 未知操作', ['action' => $action]);
            }
        }
    }

    private function doCreate(string $connectionName, array $data): void
    {
        $itemData = $data['data'] ?? [];
        if (empty($itemData)) {
            return;
        }

        $itemId = $itemData['id'] ?? 0;
        $dictTemplateId = $itemData['dict_template_id'] ?? 0;
        if (empty($itemId) || empty($dictTemplateId)) {
            return;
        }

        $exists = SyncDictItem::on($connectionName)->where('template_id', $itemId)->exists();
        if ($exists) {
            return;
        }

        // 找到该租户下对应的 sys_dict id
        $dict = SyncDict::on($connectionName)
            ->where('template_id', $dictTemplateId)
            ->first();

        if (!$dict) {
            return;
        }

        $insert = [
            'template_id'      => $itemId,
            'dict_template_id' => $dictTemplateId,
            'dict_id'          => $dict->id,
            'tenant_id'        => 0,
            'label'            => $itemData['label'] ?? '',
            'value'            => $itemData['value'] ?? '',
            'code'             => $itemData['code'] ?? '',
            'color'            => $itemData['color'] ?? '',
            'other_class'      => $itemData['other_class'] ?? '',
            'sort'             => $itemData['sort'] ?? 0,
            'enabled'          => $itemData['enabled'] ?? 1,
            'remark'           => $itemData['remark'] ?? null,
            'created_at'       => $itemData['created_at'] ?? null,
            'created_by'       => $itemData['created_by'] ?? 0,
            'updated_at'       => $itemData['updated_at'] ?? null,
            'updated_by'       => $itemData['updated_by'] ?? 0,
        ];

        SyncDictItem::on($connectionName)->insert($insert);
    }

    private function doUpdate(string $connectionName, array $data): void
    {
        $itemIds = $data['item_ids'] ?? [];
        $updateData = $data['data'] ?? [];
        if (empty($itemIds) || empty($updateData)) {
            return;
        }

        SyncDictItem::on($connectionName)
            ->whereIn('template_id', $itemIds)
            ->update($updateData);
    }

    private function doDelete(string $connectionName, array $data): void
    {
        $itemIds = $data['item_ids'] ?? [];
        if (empty($itemIds)) {
            return;
        }

        SyncDictItem::on($connectionName)
            ->whereIn('template_id', $itemIds)
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
            Logger::warning('[TenantDictItemSync] 租户数据库不可用', [
                'tenant_id' => $tenantId,
                'error'     => $e->getMessage(),
            ]);
            return false;
        }
    }
}
