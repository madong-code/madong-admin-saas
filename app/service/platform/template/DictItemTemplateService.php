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

namespace app\service\platform\template;

use app\dao\tenant\DictItemTemplateDao;
use app\model\system\dict\Dict;
use app\model\system\dict\DictItem;
use app\model\tenant\DictItemTemplate;
use app\model\tenant\Tenant;
use core\foundation\base\BaseService;
use support\Log;
use Webman\RedisQueue\Client as RedisClient;

/**
 * 字典项模板服务
 * 操作 saas_template_dict_item 表，随字典模板一起同步到租户 sys_dict_item
 */
class DictItemTemplateService extends BaseService
{
    public function __construct(DictItemTemplateDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 获取扁平列表
     */
    public function getList(array $where = []): array
    {
        return $this->dao->selectList($where)->toArray();
    }

    /**
     * 创建模板字典项（同步创建 sys_dict_item）
     */
    public function create(array $data): DictItemTemplate
    {
        return $this->dao->getModel()->getConnection()->transaction(function () use ($data) {
            $itemTemplate = $this->dao->save($data);

            $this->syncCreateToTenants($itemTemplate);

            return $itemTemplate;
        });
    }

    /**
     * 更新模板字典项（同步更新 sys_dict_item）
     */
    public function update(int $id, array $data): DictItemTemplate
    {
        return $this->dao->getModel()->getConnection()->transaction(function () use ($id, $data) {
            $itemTemplate = $this->dao->find($id);
            if (!$itemTemplate) {
                throw new \RuntimeException('字典项模板不存在');
            }
            $itemTemplate->fill($data);
            $itemTemplate->save();

            $this->syncUpdateToTenants($itemTemplate);

            return $itemTemplate;
        });
    }

    /**
     * 删除模板字典项（同步删除 sys_dict_item）
     */
    public function delete(int $id): void
    {
        $ids = [$id];

        $this->dao->getModel()->getConnection()->transaction(function () use ($ids) {
            DictItem::whereIn('template_id', $ids)->delete();
            $this->dao->delete($ids);
        });

        $this->syncDeleteToTenants($ids);
        $this->pushTenantDictItemTask('delete', $ids, []);
    }

    /**
     * 批量删除模板字典项
     */
    public function batchDelete(array $ids): void
    {
        $this->dao->getModel()->getConnection()->transaction(function () use ($ids) {
            DictItem::whereIn('template_id', $ids)->delete();
            $this->dao->delete($ids);
        });

        $this->syncDeleteToTenants($ids);
        $this->pushTenantDictItemTask('delete', $ids, []);
    }


    // ==================== 租户同步（field 模式直接执行） ====================

    private function syncCreateToTenants(DictItemTemplate $itemTemplate): void
    {
        $dictTemplateId = $itemTemplate->dict_template_id;

        // 中央库：找到 template_id 对应的中央 sys_dict（tenant_id = 0）
        $centralDict = Dict::where('template_id', $dictTemplateId)
            ->where('tenant_id', 0)
            ->first();

        if ($centralDict) {
            try {
                DictItem::create($this->buildDictItemData($itemTemplate, $centralDict->id, 0));
            } catch (\Throwable $e) {
                Log::error('创建中央字典项模板副本失败', [
                    'item_template_id' => $itemTemplate->id,
                    'error'            => $e->getMessage(),
                ]);
            }
        }

        // 已有 field 租户：按 tenant 复制
        $tenants = $this->getFieldModeTenantIds();
        foreach ($tenants as $tenantId) {
            $dict = Dict::where('template_id', $dictTemplateId)
                ->where('tenant_id', $tenantId)
                ->first();
            if (!$dict) {
                continue;
            }
            try {
                DictItem::create($this->buildDictItemData($itemTemplate, $dict->id, $tenantId));
            } catch (\Throwable $e) {
                Log::error('同步字典项模板到field租户失败', [
                    'item_template_id' => $itemTemplate->id,
                    'tenant_id'        => $tenantId,
                    'error'            => $e->getMessage(),
                ]);
            }
        }

        $this->pushTenantDictItemTask('create', [$itemTemplate->id], $itemTemplate->toArray());
    }

    private function syncUpdateToTenants(DictItemTemplate $itemTemplate): void
    {
        $update = $this->buildDictItemUpdate($itemTemplate);
        try {
            DictItem::where('template_id', $itemTemplate->id)->update($update);
        } catch (\Throwable $e) {
            Log::error('同步更新field模式租户字典项失败', [
                'item_template_id' => $itemTemplate->id,
                'error'            => $e->getMessage(),
            ]);
        }

        $this->pushTenantDictItemTask('update', [$itemTemplate->id], $update);
    }

    private function syncDeleteToTenants(array $itemTemplateIds): void
    {
        try {
            DictItem::whereIn('template_id', $itemTemplateIds)->delete();
        } catch (\Throwable $e) {
            Log::error('同步删除field模式租户字典项失败', [
                'item_template_ids' => $itemTemplateIds,
                'error'             => $e->getMessage(),
            ]);
        }
    }

    // ==================== 队列任务（database 模式） ====================

    private function pushTenantDictItemTask(string $action, array $itemTemplateIds, array $data): void
    {
        try {
            $tenantIds = $this->getDatabaseModeTenantIds();
            if (!empty($tenantIds)) {
                RedisClient::send('tenant-dict-item-sync', [
                    'action'        => $action,
                    'item_ids'      => $itemTemplateIds,
                    'data'          => $data,
                    'tenant_ids'    => $tenantIds,
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('推送字典项同步队列失败', ['error' => $e->getMessage()]);
        }
    }

    private function getFieldModeTenantIds(): array
    {
        return Tenant::where('database_mode', 'field')
            ->where('status', 'active')
            ->pluck('id')
            ->toArray();
    }

    private function getDatabaseModeTenantIds(): array
    {
        return Tenant::where('database_mode', 'database')
            ->where('status', 'active')
            ->pluck('id')
            ->toArray();
    }

    private function buildDictItemData(DictItemTemplate $itemTemplate, int|string $dictId, int|string $tenantId): array
    {
        return [
            'template_id'      => $itemTemplate->id,
            'dict_template_id' => $itemTemplate->dict_template_id,
            'dict_id'          => $dictId,
            'tenant_id'        => $tenantId,
            'label'            => $itemTemplate->label,
            'value'            => $itemTemplate->value,
            'code'             => $itemTemplate->code,
            'color'            => $itemTemplate->color,
            'other_class'      => $itemTemplate->other_class,
            'sort'             => $itemTemplate->sort ?? 0,
            'enabled'          => $itemTemplate->enabled ?? 1,
            'remark'           => $itemTemplate->remark,
            'created_at'       => $itemTemplate->created_at,
            'created_by'       => $itemTemplate->created_by,
            'updated_at'       => $itemTemplate->updated_at,
            'updated_by'       => $itemTemplate->updated_by,
        ];
    }

    private function buildDictItemUpdate(DictItemTemplate $itemTemplate): array
    {
        return [
            'label'       => $itemTemplate->label,
            'value'       => $itemTemplate->value,
            'code'        => $itemTemplate->code,
            'color'       => $itemTemplate->color,
            'other_class' => $itemTemplate->other_class,
            'sort'        => $itemTemplate->sort ?? 0,
            'enabled'     => $itemTemplate->enabled ?? 1,
            'remark'      => $itemTemplate->remark,
        ];
    }
}
