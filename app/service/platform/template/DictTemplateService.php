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

use app\dao\tenant\DictTemplateDao;
use app\model\system\dict\Dict;
use app\model\system\dict\DictItem;
use app\model\tenant\DictItemTemplate;
use app\model\tenant\DictTemplate;
use app\model\tenant\Tenant;
use core\foundation\base\BaseService;
use support\Log;
use Webman\RedisQueue\Client as RedisClient;

/**
 * 字典模板服务
 * 操作 saas_template_dict 表，app='admin' 的字典作为新建租户时的模板
 * 自动同步 sys_dict 的 template_id 关联
 */
class DictTemplateService extends BaseService
{
    public function __construct(DictTemplateDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 获取扁平列表
     */
    public function getList(): array
    {
        return $this->dao->selectList([])->toArray();
    }

    /**
     * 创建模板字典（同时创建对应 sys_dict 记录）
     */
    public function create(array $data): DictTemplate
    {
        $data['app'] = $data['app'] ?? 'admin';

        return $this->dao->getModel()->getConnection()->transaction(function () use ($data) {
            // 1. 创建模板字典
            $template = $this->dao->save($data);

            // 2. 同步创建 sys_dict 记录（仅 admin 模板需要）
            if ($template->app === 'admin') {
                $this->syncCreateToTenants($template);
            }

            return $template;
        });
    }

    /**
     * 更新模板字典（同步更新 sys_dict）
     */
    public function update(int $id, array $data): DictTemplate
    {
        return $this->dao->getModel()->getConnection()->transaction(function () use ($id, $data) {
            $template = $this->dao->find($id);
            if (!$template) {
                throw new \RuntimeException('字典模板不存在');
            }
            $template->fill($data);
            $template->save();

            // 同步更新所有关联的 sys_dict（通过 template_id）
            $this->syncUpdateToTenants($template);

            return $template;
        });
    }

    /**
     * 删除模板字典（同步删除 sys_dict，并通知各租户）
     */
    public function delete(int $id): void
    {
        $ids = [$id];

        $this->dao->getModel()->getConnection()->transaction(function () use ($ids) {
            // 1. 删除所有关联的模板项
            DictItemTemplate::whereIn('dict_template_id', $ids)->delete();

            // 2. 删除所有关联的 sys_dict 记录（通过 template_id）
            Dict::whereIn('template_id', $ids)->delete();

            // 3. 删除模板字典
            $this->dao->delete($ids);
        });

        // 4. field 模式租户：直接删除租户字典
        $this->syncDeleteToTenants($ids);

        // 5. database 模式租户：队列异步
        $this->pushTenantDictTask('delete', $ids, []);
    }

    /**
     * 批量删除模板字典
     */
    public function batchDelete(array $ids): void
    {
        $this->dao->getModel()->getConnection()->transaction(function () use ($ids) {
            DictItemTemplate::whereIn('dict_template_id', $ids)->delete();
            Dict::whereIn('template_id', $ids)->delete();
            $this->dao->delete($ids);
        });

        $this->syncDeleteToTenants($ids);
        $this->pushTenantDictTask('delete', $ids, []);
    }

    // ==================== 租户同步（field 模式直接执行） ====================

    /**
     * field 模式租户：创建字典
     */
    private function syncCreateToTenants(DictTemplate $template): void
    {
        // 中央库创建一条 tenant_id 为空的记录（与菜单模板保持一致）
        try {
            Dict::create($this->buildDictData($template, 0));
        } catch (\Throwable $e) {
            Log::error('创建中央字典模板副本失败', [
                'template_id' => $template->id,
                'error'       => $e->getMessage(),
            ]);
        }

        // 已有 field 租户：各自已通过开通流程复制，无需再建；新租户由开通流程复制
        $this->pushTenantDictTask('create', [$template->id], $template->toArray());
    }

    /**
     * field 模式租户：同步更新字典（按 template_id 更新所有租户副本）
     */
    private function syncUpdateToTenants(DictTemplate $template): void
    {
        $update = $this->buildDictUpdate($template);
        try {
            Dict::where('template_id', $template->id)->update($update);
        } catch (\Throwable $e) {
            Log::error('同步更新field模式租户字典失败', [
                'template_id' => $template->id,
                'error'       => $e->getMessage(),
            ]);
        }

        $this->pushTenantDictTask('update', [$template->id], $update);
    }

    /**
     * field 模式租户：同步删除字典（按 template_id 删除所有租户副本）
     */
    private function syncDeleteToTenants(array $templateIds): void
    {
        try {
            Dict::whereIn('template_id', $templateIds)->delete();
        } catch (\Throwable $e) {
            Log::error('同步删除field模式租户字典失败', [
                'template_ids' => $templateIds,
                'error'        => $e->getMessage(),
            ]);
        }
    }

    // ==================== 队列任务（database 模式） ====================

    private function pushTenantDictTask(string $action, array $templateIds, array $data): void
    {
        try {
            $tenantIds = $this->getDatabaseModeTenantIds();
            if (!empty($tenantIds)) {
                RedisClient::send('tenant-dict-sync', [
                    'action'       => $action,
                    'template_ids' => $templateIds,
                    'data'         => $data,
                    'tenant_ids'   => $tenantIds,
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('推送字典同步队列失败', ['error' => $e->getMessage()]);
        }
    }

    private function getDatabaseModeTenantIds(): array
    {
        return Tenant::where('database_mode', 'database')
            ->where('status', 'active')
            ->pluck('id')
            ->toArray();
    }

    private function buildDictData(DictTemplate $template, int|string $tenantId): array
    {
        return [
            'template_id' => $template->id,
            'tenant_id'   => $tenantId,
            'group_code'  => $template->group_code,
            'name'        => $template->name,
            'code'        => $template->code,
            'sort'        => $template->sort ?? 0,
            'data_type'   => $template->data_type ?? 1,
            'description' => $template->description,
            'enabled'     => $template->enabled ?? 1,
            'created_at'  => $template->created_at,
            'created_by'  => $template->created_by,
            'updated_at'  => $template->updated_at,
            'updated_by'  => $template->updated_by,
        ];
    }

    private function buildDictUpdate(DictTemplate $template): array
    {
        return [
            'group_code'  => $template->group_code,
            'name'        => $template->name,
            'code'        => $template->code,
            'sort'        => $template->sort ?? 0,
            'data_type'   => $template->data_type ?? 1,
            'description' => $template->description,
            'enabled'     => $template->enabled ?? 1,
        ];
    }
}
