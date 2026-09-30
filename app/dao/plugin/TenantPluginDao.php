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
 * Official Website: https://madong.tech
 */

namespace app\dao\plugin;

use app\model\plugin\TenantPlugin;
use app\model\plugin\TenantPluginInstall;
use core\foundation\base\BaseDao;
use madong\query\QueryBuilderHelper;

/**
 * 租户插件数据访问层(授权治理)
 *
 * 仅承载授权治理类查询; 运行态字段(status/installed_at/sync_status/isolation_mode/
 * version/deprecated_version/config) 已拆分到 TenantPluginInstall, 相关查询见
 * TenantPluginInstallDao。本 DAO 返回的 TenantPlugin 模型通过 install 关系代理运行态字段,
 * 调用方用 with('install') 后可像以前一样读取 $record->version 等。
 *
 * @author Mr.April
 * @since  1.0
 */
class TenantPluginDao extends BaseDao
{
    protected function setModel(): string
    {
        return TenantPlugin::class;
    }

    /**
     * 获取租户插件列表（分页）
     */
    public function getList(array $where = [], int $page = 1, int $limit = 15): array
    {
        $query = $this->query()->with('install');

        if (isset($where['filters']) && is_array($where['filters'])) {
            QueryBuilderHelper::applyFiltersToQuery($query, $where['filters']);
        }

        $total = $query->count();
        $list  = $query->orderBy('created_at', 'desc')
            ->offset(($page - 1) * $limit)
            ->limit($limit)
            ->get()
            ->toArray();

        return [
            'total' => $total,
            'items' => $list,
        ];
    }

    /**
     * 根据租户ID和插件key查询(预加载 install 运行态)
     */
    public function findByTenantAndKey(int|string $tenantId, string $pluginKey): ?TenantPlugin
    {
        return $this->query()
            ->with('install')
            ->where('tenant_id', $tenantId)
            ->where('plugin_key', $pluginKey)
            ->first();
    }

    /**
     * 获取租户已授权的所有插件key列表
     */
    public function getPluginKeysByTenant(int|string $tenantId): array
    {
        return $this->query()
            ->where('tenant_id', $tenantId)
            ->pluck('plugin_key')
            ->toArray();
    }

    /**
     * 获取租户已安装且启用的插件列表(运行态, 来自 install 表)
     */
    public function getActivePluginsByTenant(int|string $tenantId): array
    {
        return TenantPluginInstall::query()
            ->where('tenant_id', $tenantId)
            ->where('status', 1)
            ->get()
            ->toArray();
    }

    /**
     * WP1: 占用清单聚合 - 查某插件已启用的租户(运行态)
     */
    public function getInstalledTenants(string $pluginKey): array
    {
        return TenantPluginInstall::query()
            ->where('plugin_key', $pluginKey)
            ->where('status', 1)
            ->get(['tenant_id', 'tenant_name', 'isolation_mode', 'version'])
            ->toArray();
    }

    /**
     * 获取某插件已授权的租户(授权治理表, auth_status ∈ {authorized, trial})
     *
     * 用于 dashboard 卡片的"未授权"统计, 与运行态(是否已安装)无关。
     */
    public function getAuthorizedTenants(string $pluginKey): array
    {
        return $this->query()
            ->where('plugin_key', $pluginKey)
            ->whereIn('auth_status', ['authorized', 'trial'])
            ->get(['tenant_id'])
            ->toArray();
    }

    /**
     * WP1: 真实安装者数量
     */
    public function countInstalledTenants(string $pluginKey): int
    {
        return TenantPluginInstall::query()
            ->where('plugin_key', $pluginKey)
            ->where('status', 1)
            ->count();
    }

    /**
     * WP1: 版本比对, 找出落后于 latestVersion 的租户(运行态)
     */
    public function getUpgradableTenants(string $pluginKey, string $latestVersion): array
    {
        return TenantPluginInstall::query()
            ->where('plugin_key', $pluginKey)
            ->where('status', 1)
            ->where(function ($q) use ($latestVersion) {
                $q->whereNull('version')
                    ->orWhere('version', '')
                    ->orWhere('version', '<', $latestVersion);
            })
            ->get(['tenant_id', 'tenant_name', 'isolation_mode', 'version'])
            ->toArray();
    }

    /**
     * WP1: 平台更新同步时, 过滤真实安装者 + 版本落后者(is_purchased 在授权表)
     */
    public function getStaleInstallers(string $pluginKey, string $latestVersion): array
    {
        return TenantPluginInstall::query()
            ->where('plugin_key', $pluginKey)
            ->where('status', 1)
            ->whereExists(function ($q) {
                $q->select(\Illuminate\Support\Facades\DB::raw('1'))
                    ->from('saas_tenant_plugin')
                    ->whereColumn('saas_tenant_plugin.tenant_id', 'saas_tenant_plugin_install.tenant_id')
                    ->whereColumn('saas_tenant_plugin.plugin_key', 'saas_tenant_plugin_install.plugin_key')
                    ->where('is_purchased', 1);
            })
            ->where(function ($q) use ($latestVersion) {
                $q->whereNull('version')
                    ->orWhere('version', '')
                    ->orWhere('version', '<', $latestVersion);
            })
            ->get(['tenant_id', 'isolation_mode', 'version'])
            ->toArray();
    }
}
