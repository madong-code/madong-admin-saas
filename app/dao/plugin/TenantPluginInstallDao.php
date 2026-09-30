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

use app\model\plugin\TenantPluginInstall;
use core\foundation\base\BaseDao;

/**
 * 租户插件安装运行态数据访问层
 *
 * 与 TenantPluginDao(授权治理) 互补, 仅承载运行态字段查询。
 *
 * @author Mr.April
 * @since  1.0
 */
class TenantPluginInstallDao extends BaseDao
{
    protected function setModel(): string
    {
        return TenantPluginInstall::class;
    }

    public function findByTenantAndKey(int|string $tenantId, string $pluginKey): ?TenantPluginInstall
    {
        return $this->query()
            ->where('tenant_id', $tenantId)
            ->where('plugin_key', $pluginKey)
            ->first();
    }

    /**
     * 某插件已安装(启用)的租户列表
     */
    public function getInstalledTenants(string $pluginKey): array
    {
        return $this->query()
            ->where('plugin_key', $pluginKey)
            ->where('status', 1)
            ->get(['tenant_id', 'tenant_name', 'isolation_mode', 'version'])
            ->toArray();
    }

    /**
     * 版本落后于 latestVersion 的租户列表
     */
    public function getUpgradableTenants(string $pluginKey, string $latestVersion): array
    {
        return $this->query()
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
     * 已购买且版本落后的安装者(用于平台更新同步)
     *
     * is_purchased 在授权表, 用 whereExists 关联 saas_tenant_plugin。
     */
    public function getStaleInstallers(string $pluginKey, string $latestVersion): array
    {
        return $this->query()
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

    /**
     * 某租户已启用(安装)的插件运行记录
     */
    public function getActiveByTenant(int|string $tenantId): array
    {
        return $this->query()
            ->where('tenant_id', $tenantId)
            ->where('status', 1)
            ->get()
            ->toArray();
    }
}
