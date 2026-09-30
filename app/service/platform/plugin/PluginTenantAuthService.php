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

namespace app\service\platform\plugin;

use app\dao\plugin\TenantPluginDao;
use app\model\plugin\TenantPlugin;
use core\foundation\base\BaseService;
use core\foundation\exception\handler\AdminException;
use core\io\uuid\Snowflake;
use support\Container;

/**
 * 平台端租户授权服务 - 管理 saas_tenant_plugin 表
 */
class PluginTenantAuthService extends BaseService
{
    public function __construct(TenantPluginDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 获取租户授权列表（系统已安装插件 × 租户授权状态）
     */
    public function getAuthList(string $tenantId): array
    {
        // 获取系统已安装的插件列表
        $pluginService = Container::make(\app\service\core\plugin\PluginService::class);
        $localModules = $pluginService->getLocalModules();

        $installedPlugins = [];
        foreach ($localModules as $module) {
            if (!$module['is_installed']) {
                continue;
            }
            $installedPlugins[] = [
                'key'         => $module['name'],
                'title'       => $module['title'] ?? $module['name'],
                'version'     => $module['version'],
                'author'      => $module['author'],
                'icon'        => $module['icon'],
                'description' => $module['description'],
            ];
        }

        // 获取该租户已有授权记录
        $authorizedKeys = $this->dao->getPluginKeysByTenant($tenantId);

        // 合并数据
        $items = [];
        foreach ($installedPlugins as $plugin) {
            $isAuthorized = in_array($plugin['key'], $authorizedKeys);
            $tenantRecord = null;
            if ($isAuthorized) {
                $tenantRecord = $this->dao->findByTenantAndKey($tenantId, $plugin['key']);
            }

            $items[] = [
                'plugin_key'   => $plugin['key'],
                'plugin_title' => $plugin['title'],
                'version'      => $plugin['version'],
                'author'       => $plugin['author'],
                'icon'         => $plugin['icon'],
                'description'  => $plugin['description'],
                'auth_status'  => $tenantRecord ? $tenantRecord->auth_status : 'none',
                'is_installed' => $tenantRecord && $tenantRecord->installed_at ? 1 : 0,
                'installed_at' => $tenantRecord ? $tenantRecord->installed_at : null,
            ];
        }

        return [
            'list'  => $items,
            'total' => count($items),
        ];
    }

    /**
     * 增量授权租户插件
     *
     * 只新增 / 更新传入列表中的授权, 不会影响该租户已有的其他插件授权。
     * (修复此前把"全量替换"语义错用到增量授权上, 导致授权新插件把旧授权一并删掉的覆盖 bug。)
     *
     * 如需取消授权, 请使用 revokeAuth()。
     */
    public function setAuth(string $tenantId, array $pluginKeys, string $authStatus = 'authorized'): void
    {
        $existingKeys = $this->dao->getPluginKeysByTenant($tenantId);

        // 需要新增的授权
        $toAdd = array_diff($pluginKeys, $existingKeys);
        foreach ($toAdd as $key) {
            $this->dao->save([
                'id'          => Snowflake::generate(),
                'tenant_id'   => $tenantId,
                'plugin_key'  => $key,
                'auth_status' => $authStatus,
                'status'      => 1,
                'is_purchased' => 0,
            ]);
        }

        // 更新已有记录的 auth_status
        $toUpdate = array_intersect($pluginKeys, $existingKeys);
        foreach ($toUpdate as $key) {
            $record = $this->dao->findByTenantAndKey($tenantId, $key);
            if ($record) {
                $this->dao->update($record->id, ['auth_status' => $authStatus]);
            }
        }
    }

    /**
     * 批量取消租户插件授权
     *
     * 与 setAuth() 对称, 用于显式取消授权(只删除传入的插件 key 记录,
     * 不会影响该租户的其他授权)。
     */
    public function revokeAuth(string $tenantId, array $pluginKeys): void
    {
        foreach ($pluginKeys as $key) {
            $record = $this->dao->findByTenantAndKey($tenantId, $key);
            if ($record) {
                \app\model\plugin\TenantPluginInstall::where('tenant_id', $tenantId)
                    ->where('plugin_key', $key)
                    ->delete();
                $this->dao->delete($record->id);
            }
        }
    }

    /**
     * 解析 SSE 批量升级的目标租户
     *
     * - tenantIds 非空: 直接以其为准(再剔除 excludeIds)
     * - 否则: 取该插件全部"可升级"租户, 再剔除 excludeIds
     *   全选场景前端不传 ID 列表交由后端计算, 避免 EventSource(GET) 的 URL 长度限制。
     *
     * @return array 目标租户ID列表
     */
    public function resolveUpgradeTargets(
        string $code,
        string $version,
        array $tenantIds = [],
        array $excludeIds = []
    ): array {
        if (!empty($tenantIds)) {
            $tenantIds = array_map('strval', $tenantIds);
            return empty($excludeIds)
                ? $tenantIds
                : array_values(array_diff($tenantIds, array_map('strval', $excludeIds)));
        }

        $rows = $this->dao->getUpgradableTenants($code, $version);
        $ids  = [];
        foreach ($rows as $r) {
            $tid = (string)($r['tenant_id'] ?? '');
            if ($tid !== '') {
                $ids[] = $tid;
            }
        }

        if (!empty($excludeIds)) {
            $ids = array_values(array_diff($ids, array_map('strval', $excludeIds)));
        }

        return $ids;
    }

    // ============================================================
    // WP6: 平台矩阵 / 升级统计 / 占用清单
    // ============================================================

    /**
     * 获取平台 × 租户授权矩阵
     *
     * 输出: { plugins: [...], tenants: [...], matrix: { [pluginKey]: { [tenantId]: {auth,installed,version,isolation,upgradable} } } }
     *
     * 供 platform 控制台"插件分发中心"展示
     */
    public function getMatrix(array $pluginKeys = [], array $tenantIds = []): array
    {
        $pluginService = Container::make(\app\service\core\plugin\PluginService::class);
        $modules = $pluginService->getLocalModules();
        $installedPluginKeys = [];
        foreach ($modules as $m) {
            if (!empty($m['is_installed'])) {
                $installedPluginKeys[] = $m['name'];
            }
        }
        if (!empty($pluginKeys)) {
            $installedPluginKeys = array_values(array_intersect($installedPluginKeys, $pluginKeys));
        }

        $tenantQuery = \app\model\tenant\Tenant::withoutGlobalScopes()->where('status', \app\model\tenant\Tenant::STATUS_ACTIVE);
        if (!empty($tenantIds)) {
            $tenantQuery->whereIn('id', $tenantIds);
        }
        $tenants = $tenantQuery->get(['id', 'code', 'name', 'database_mode', 'expire_time'])->toArray();

        // 授权治理 + 运行态分两张表查询, 分别索引后合并
        $tenantIdList = !empty($tenantIds)
            ? $tenantIds
            : \app\model\tenant\Tenant::withoutGlobalScopes()->pluck('id')->toArray();

        $authRows = $this->dao->query()
            ->whereIn('plugin_key', $installedPluginKeys ?: [''])
            ->whereIn('tenant_id', $tenantIdList)
            ->get(['tenant_id', 'plugin_key', 'auth_status', 'is_purchased', 'status', 'installed_at', 'version', 'isolation_mode'])
            ->toArray();
        $installRows = \app\model\plugin\TenantPluginInstall::query()
            ->whereIn('plugin_key', $installedPluginKeys ?: [''])
            ->whereIn('tenant_id', $tenantIdList)
            ->get(['tenant_id', 'plugin_key', 'status', 'version', 'isolation_mode'])
            ->toArray();

        $authIndex = [];
        foreach ($authRows as $r) {
            $authIndex[$r['plugin_key'] . '::' . $r['tenant_id']] = $r;
        }
        $installIndex = [];
        foreach ($installRows as $r) {
            $installIndex[$r['plugin_key'] . '::' . $r['tenant_id']] = $r;
        }

        $matrix = [];
        foreach ($installedPluginKeys as $pk) {
            $matrix[$pk] = [];
            foreach ($tenants as $t) {
                $a = $authIndex[$pk . '::' . $t['id']] ?? null;
                $i = $installIndex[$pk . '::' . $t['id']] ?? null;
                // 运行态表优先；过渡期授权表仍保留运行字段，缺失运行记录时兜底
                $isInstalled = $i
                    ? (int)($i['status'] === 1)
                    : ($a ? (int)((int)$a['status'] === 1 && !empty($a['installed_at'])) : 0);
                $matrix[$pk][$t['id']] = [
                    'auth_status'    => $a['auth_status'] ?? 'none',
                    'is_installed'   => $isInstalled,
                    'is_purchased'   => $a ? (int)$a['is_purchased'] : 0,
                    'version'        => $i['version'] ?? ($a['version'] ?? null),
                    'isolation_mode' => $i['isolation_mode'] ?? ($a['isolation_mode'] ?? null),
                ];
            }
        }

        return [
            'plugins'  => $installedPluginKeys,
            'tenants'  => $tenants,
            'matrix'   => $matrix,
        ];
    }

    /**
     * 升级统计 (WP6)
     *
     * 按 plugin 维度聚合:
     *   - installed:  已装租户数
     *   - upgradable: 版本落后租户数
     *   - ungranted:  未授权租户数
     *
     * 用于平台 dashboard 卡片与"批量升级"按钮上的数字徽标
     */
    public function getStats(array $pluginKeys = []): array
    {
        $pluginService = Container::make(\app\service\core\plugin\PluginService::class);
        $modules = $pluginService->getLocalModules();
        $installedPluginKeys = [];
        $latestVersionMap = [];
        foreach ($modules as $m) {
            if (!empty($m['is_installed'])) {
                $installedPluginKeys[] = $m['name'];
                $latestVersionMap[$m['name']] = (string)($m['version'] ?? '1.0.0');
            }
        }
        if (!empty($pluginKeys)) {
            $installedPluginKeys = array_values(array_intersect($installedPluginKeys, $pluginKeys));
        }

        $activeTenants = \app\model\tenant\Tenant::withoutGlobalScopes()
            ->where('status', \app\model\tenant\Tenant::STATUS_ACTIVE)
            ->pluck('id')
            ->toArray();

        $result = [];
        foreach ($installedPluginKeys as $pk) {
            $latest = $latestVersionMap[$pk];
            $upgradable = $this->dao->getUpgradableTenants($pk, $latest);
            $installed  = $this->dao->getInstalledTenants($pk);
            $authorized = $this->dao->getAuthorizedTenants($pk);

            $result[] = [
                'plugin_key'     => $pk,
                'latest_version' => $latest,
                'installed_count'   => count($installed),
                'upgradable_count'  => count($upgradable),
                'ungranted_count'   => max(0, count($activeTenants) - count($authorized)),
            ];
        }
        return $result;
    }

    /**
     * 占用清单 (WP6)
     *
     * 列出"插件被哪些租户占用", 供 platform "插件分发中心" 强制卸载前的展示
     *
     * @return array
     */
    public function getOccupyingTenants(string $pluginKey): array
    {
        $rows = $this->dao->getInstalledTenants($pluginKey);
        $tenantIds = array_column($rows, 'tenant_id');
        if (empty($tenantIds)) {
            return ['plugin_key' => $pluginKey, 'count' => 0, 'items' => []];
        }
        $tenants = \app\model\tenant\Tenant::withoutGlobalScopes()
            ->whereIn('id', $tenantIds)
            ->get(['id', 'code', 'name', 'database_mode', 'expire_time'])
            ->keyBy('id')
            ->toArray();

        $items = [];
        foreach ($rows as $r) {
            $tid = $r['tenant_id'];
            $t = $tenants[$tid] ?? null;
            $items[] = [
                'tenant_id'      => (string)$tid,
                'tenant_code'    => $t['code'] ?? null,
                'tenant_name'    => $t['name'] ?? null,
                'isolation_mode' => $r['isolation_mode'] ?? null,
                'version'        => $r['version'] ?? null,
                'expire_time'    => $t['expire_time'] ?? null,
            ];
        }

        return [
            'plugin_key' => $pluginKey,
            'count'      => count($items),
            'items'      => $items,
        ];
    }
}
