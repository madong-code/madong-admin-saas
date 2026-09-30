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

use app\dao\tenant\ConfigTemplateDao;
use app\model\system\config\Config;
use app\model\tenant\ConfigTemplate;
use app\model\tenant\Tenant;
use core\foundation\base\BaseService;
use core\business\tenant\scope\TenantScope;
use support\Log;
use Webman\RedisQueue\Client as RedisClient;

/**
 * 配置模板服务
 *
 * 操作 saas_template_config 表，is_sync=1 的配置作为新建租户时的默认配置。
 * 自动同步 sys_config 的 template_id 关联。
 */
class ConfigTemplateService extends BaseService
{
    public function __construct(ConfigTemplateDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 获取配置模板列表
     */
    public function getList(string $groupCode = ''): array
    {
        $where = [];
        if (!empty($groupCode)) {
            $where['group_code'] = $groupCode;
        }
        return $this->dao->selectList($where)->toArray();
    }

    /**
     * 按分组获取启用的配置模板（keyBy code）
     */
    public function getByGroup(string $groupCode): array
    {
        if (empty($groupCode)) {
            return [];
        }
        $list = $this->dao->selectList([
            'group_code' => $groupCode,
            'enabled'    => 1,
        ])->toArray();

        $result = [];
        foreach ($list as $item) {
            $content = $item['content'] ?? null;
            if (is_string($content) && !empty($content)) {
                $decoded = json_decode($content, true);
                $result[$item['code']] = json_last_error() === JSON_ERROR_NONE ? $decoded : $content;
            } else {
                $result[$item['code']] = $content;
            }
        }
        return $result;
    }

    /**
     * 创建配置模板（同时同步到 field 模式租户）
     */
    public function create(array $data): ConfigTemplate
    {
        return $this->dao->getModel()->getConnection()->transaction(function () use ($data) {
            $template = $this->dao->save($data);

            // 如果标记为同步，立即同步到 field 模式租户
            if (!empty($data['is_sync'])) {
                $this->syncToFieldTenants($template);
            }

            return $template;
        });
    }

    /**
     * 更新配置模板（同步更新 field 模式租户）
     */
    public function update(int $id, array $data): ConfigTemplate
    {
        return $this->dao->getModel()->getConnection()->transaction(function () use ($id, $data) {
            $template = $this->dao->find($id);
            if (!$template) {
                throw new \RuntimeException('配置模板不存在');
            }
            $template->fill($data);
            $template->save();

            // 同步更新所有关联的 sys_config（通过 template_id）
            $this->syncToFieldTenants($template);

            // database 模式：通过队列异步处理
            $this->pushTenantConfigUpdateTask($id, $data);

            return $template;
        });
    }

    /**
     * 删除配置模板（同步删除 sys_config）
     */
    public function delete(int $id): void
    {
        $this->dao->getModel()->getConnection()->transaction(function () use ($id) {
            // 1. 删除所有关联的 sys_config 记录
            Config::withoutGlobalScope(TenantScope::class)
                ->where('template_id', $id)
                ->delete();

            // 2. 删除模板
            $this->dao->delete($id);
        });

        // database 模式租户：队列异步
        $this->pushTenantConfigDeleteTask($id);
    }

    /**
     * 批量删除配置模板
     */
    public function batchDelete(array $ids): void
    {
        $this->dao->getModel()->getConnection()->transaction(function () use ($ids) {
            Config::withoutGlobalScope(TenantScope::class)
                ->whereIn('template_id', $ids)
                ->delete();
            $this->dao->delete($ids);
        });

        foreach ($ids as $id) {
            $this->pushTenantConfigDeleteTask((int)$id);
        }
    }


    // ==================== 租户同步（field 模式直接执行） ====================

    /**
     * field 模式租户：同步更新配置
     */
    private function syncToFieldTenants(ConfigTemplate $template): void
    {
        try {
            $updateData = array_intersect_key($template->toArray(), array_flip([
                'group_code', 'code', 'name', 'content',
                'is_sys', 'enabled', 'sort', 'remark',
            ]));

            Config::withoutGlobalScope(TenantScope::class)
                ->where('template_id', $template->id)
                ->update($updateData);
        } catch (\Throwable $e) {
            Log::error('同步配置到field模式租户失败', [
                'template_id' => $template->id,
                'error'       => $e->getMessage(),
            ]);
        }
    }

    // ==================== 队列任务（database 模式） ====================

    /**
     * 推送配置更新任务到队列（database 模式租户）
     */
    private function pushTenantConfigUpdateTask(int $templateId, array $data): void
    {
        try {
            $tenantIds = $this->getDatabaseModeTenantIds();
            if (!empty($tenantIds)) {
                RedisClient::send('tenant-config-sync', [
                    'action'      => 'update',
                    'template_id' => $templateId,
                    'data'        => $data,
                    'tenant_ids'  => $tenantIds,
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('推送配置更新队列失败', ['error' => $e->getMessage()]);
        }
    }

    /**
     * 推送配置删除任务到队列（database 模式租户）
     */
    private function pushTenantConfigDeleteTask(int $templateId): void
    {
        try {
            $tenantIds = $this->getDatabaseModeTenantIds();
            if (!empty($tenantIds)) {
                RedisClient::send('tenant-config-sync', [
                    'action'      => 'delete',
                    'template_id' => $templateId,
                    'tenant_ids'  => $tenantIds,
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('推送配置删除队列失败', ['error' => $e->getMessage()]);
        }
    }

    /**
     * 获取所有 database 模式的租户ID
     */
    private function getDatabaseModeTenantIds(): array
    {
        return Tenant::where('database_mode', 'database')
            ->where('status', 'active')
            ->pluck('id')
            ->toArray();
    }
}
