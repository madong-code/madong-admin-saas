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

namespace app\service\admin\plugin;

use app\dao\plugin\TenantPluginDao;
use app\model\plugin\TenantPluginInstall;
use app\model\tenant\Tenant;
use app\service\core\plugin\PluginBaseService;
use app\service\core\plugin\PluginService;
use app\service\core\plugin\TenantMenuSyncService;
use app\service\core\plugin\TenantPluginConfigService;
use app\service\core\plugin\TenantPluginMigrationService;
use core\business\tenant\TenantConnectionManager;
use core\business\tenant\context\TenantContext;
use core\foundation\exception\handler\PluginException;
use core\foundation\tool\Sse;
use core\io\uuid\Snowflake;
use support\Container;
use support\Db;

/**
 * 租户插件服务
 *
 * 负责租户插件的权限检查、列表、安装编排、卸载编排
 *
 * @author Mr.April
 * @since  1.0
 */
class TenantPluginService extends PluginBaseService
{
    protected PluginService $pluginService;

    public function __construct(
        TenantPluginDao $dao,
        PluginService $pluginService
    ) {
        parent::__construct();
        $this->dao = $dao;
        $this->pluginService = $pluginService;
    }

    /**
     * 获取租户可用的插件列表（平台已安装的插件）
     *
     * 授权状态对接平台授权列表(PluginTenantAuthService::getAuthList)：
     *   - is_authorized(已授权)：租户在 saas_tenant_plugin 有授权记录(auth_status=authorized)或已安装记录
     *   - is_installed(已安装)  ：租户实际安装(status=1 且有 installed_at)
     *   - is_purchased(兼容)    ：与 is_authorized 一致（供前端"已授权"Tab 使用）
     */
    public function getAvailablePlugins(string|int $tenantId, string $keyword = ''): array
    {
        $localModules = $this->pluginService->getLocalModules();

        $items = [];
        foreach ($localModules as $module) {
            if (!$module['is_installed']) {
                continue;
            }

            if ($keyword && !str_contains(strtolower($module['name']), strtolower($keyword))
                && !str_contains(strtolower($module['title'] ?? ''), strtolower($keyword))) {
                continue;
            }

            $tenantRecord = $this->dao->findByTenantAndKey($tenantId, $module['name']);

            // 已授权：有平台授权记录(auth_status=authorized) 或 已安装记录(兼容旧数据 is_purchased=1)
            $isAuthorized = $tenantRecord && (
                (string)$tenantRecord->auth_status === 'authorized'
                || (int)$tenantRecord->is_purchased === 1
            );
            // 已安装：状态启用且有安装时间
            $isInstalled = $tenantRecord && (int)$tenantRecord->status === 1 && !empty($tenantRecord->installed_at);

            $items[] = [
                'code'          => $module['code'],
                'name'          => $module['name'],
                'version'       => $module['version'],
                'description'   => $module['description'],
                'author'        => $module['author'],
                'icon'          => $module['icon'],
                'cover'         => $module['cover'],
                'type'          => $module['type'],
                'is_authorized' => (int)$isAuthorized,
                'is_installed'  => (int)$isInstalled,
                'is_purchased'  => (int)$isAuthorized,
                'auth_status'   => $tenantRecord ? $tenantRecord->auth_status : 'none',
                'installed_at'  => $isInstalled ? $tenantRecord->installed_at : null,
                'expires_at'    => $tenantRecord ? $tenantRecord->expires_at : null,
                // 综合判定: 平台允许升级 + 未被租户忽略 + 版本更高
                'has_update'    => $isInstalled && $tenantRecord
                    ? $tenantRecord->canUpgrade((string)$module['version'])[0]
                    : false,
                'can_install'   => $this->canInstall($tenantId, $module['name']),
            ];
        }

        return $items;
    }

    /**
     * 检查租户是否可以安装插件
     *
     * 对接平台授权列表：仅"平台已安装 + 租户已授权 + 未安装"才允许安装。
     * 判定顺序：平台已安装 → 已安装 → 平台授权。
     */
    public function canInstall(string|int $tenantId, string $pluginKey): array
    {
        $pluginDir = $this->plugin_path . DIRECTORY_SEPARATOR . $pluginKey;
        $installedConfigFile = $pluginDir . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'installed.php';
        if (!is_file($installedConfigFile)) {
            return ['allowed' => false, 'reason' => '插件尚未被平台安装'];
        }

        $record = $this->dao->findByTenantAndKey($tenantId, $pluginKey);

        // 已安装 → 不允许重复安装
        if ($record && (int)$record->status === 1 && !empty($record->installed_at)) {
            return ['allowed' => false, 'reason' => '该租户已安装此插件'];
        }

        // 平台授权检查：有授权记录(auth_status=authorized) 或 已安装记录(兼容旧数据)
        $authorized = $record && (
            (string)$record->auth_status === 'authorized'
            || (int)$record->is_purchased === 1
        );
        if (!$authorized) {
            return ['allowed' => false, 'reason' => '该租户未获得平台授权，请先在平台端授权'];
        }

        return ['allowed' => true, 'reason' => ''];
    }

    /**
     * 切换租户插件启用/停用状态
     */
    public function toggleStatus(string|int $tenantId, string $pluginKey, int $status): void
    {
        $record = $this->dao->findByTenantAndKey($tenantId, $pluginKey);
        if (!$record) {
            throw new PluginException('插件记录不存在');
        }
        $this->dao->update($record->id, ['status' => $status]);
    }

    /**
     * 获取插件详情（含隔离模式信息）
     */
    public function getDetail(string|int $tenantId, string $pluginKey): array
    {
        $record = $this->dao->findByTenantAndKey($tenantId, $pluginKey);
        $pluginDir = $this->plugin_path . DIRECTORY_SEPARATOR . $pluginKey;

        $pluginInfo = $this->getPluginConfig($pluginKey);

        // 已授权：有平台授权记录(auth_status=authorized) 或 已安装记录(兼容旧数据)
        $isAuthorized = $record && (
            (string)$record->auth_status === 'authorized'
            || (int)$record->is_purchased === 1
        );
        // 已安装：状态启用且有安装时间
        $isInstalled = $record && (int)$record->status === 1 && !empty($record->installed_at);

        return [
            'key'               => $pluginKey,
            'title'             => $pluginInfo['title'] ?? '',
            'version'           => $pluginInfo['version'] ?? '',
            'description'       => $pluginInfo['desc'] ?? '',
            'author'            => $pluginInfo['author'] ?? '',
            'icon'              => $pluginInfo['icon'] ?? '',
            'cover'             => $pluginInfo['cover'] ?? '',
            'is_authorized'     => (int)$isAuthorized,
            'is_installed'      => (int)$isInstalled,
            'auth_status'       => $record ? $record->auth_status : 'none',
            'installed_at'      => $isInstalled ? $record->installed_at : null,
            'expires_at'        => $record ? $record->expires_at : null,
            'isolation_mode'    => $this->getIsolationMode($tenantId),
            'has_migrations'    => is_dir($pluginDir . '/resource/database/migrations'),
            'migration_count'   => $this->countMigrations($pluginKey),
        ];
    }

    /**
     * 卸载前预览清理计划
     */
    public function getCleanupPlan(string|int $tenantId, string $pluginKey): array
    {
        $pluginDir = $this->plugin_path . DIRECTORY_SEPARATOR . $pluginKey;
        $isolationMode = $this->getIsolationMode($tenantId);
        $pluginInfo = $this->getPluginConfig($pluginKey);

        $items = [];

        // 清理菜单
        $items[] = [
            'action' => '清理菜单',
            'detail' => '删除插件注册的菜单项',
            'level'  => 'safe',
        ];

        // 重置租户插件记录（保留授权，清除安装状态）
        $items[] = [
            'action' => '重置租户记录',
            'detail' => '保留平台授权，清除安装状态（installed_at/版本）',
            'level'  => 'safe',
        ];

        // 库隔离模式额外操作
        if ($isolationMode === 'database') {
            $migrationCount = $this->countMigrations($pluginKey);
            $items[] = [
                'action' => '回滚迁移',
                'detail' => "回滚 {$migrationCount} 个数据库迁移文件",
                'level'  => 'caution',
            ];

            $dropTables = $pluginInfo['uninstall']['drop_tables'] ?? false;
            if ($dropTables) {
                $items[] = [
                    'action' => '删除业务表',
                    'detail' => '删除插件创建的业务表（根据 uninstall.drop_tables 配置）',
                    'level'  => 'danger',
                ];
            }
        }

        return [
            'mode'  => $isolationMode,
            'items' => $items,
        ];
    }

    /**
     * 租户安装前检查（与单体不同：不安装代码，仅确认场景信息）
     *
     * 返回内容按租户隔离模式区分：
     *   - 插件版本 / 菜单源 / 平台授权 / 租户类型 / 安装状态 / 数据库迁移 / 依赖 / 版本更新
     *
     * @return array{items: array, isolation_mode: string, version: string}
     */
    public function checkEnvironment(string $pluginKey, string|int $tenantId): array
    {
        $pluginInfo = $this->getPluginConfig($pluginKey);
        $version = (string)($pluginInfo['version'] ?? '1.0.0');
        $isolationMode = $this->getIsolationMode($tenantId);
        $existing = $this->dao->findByTenantAndKey($tenantId, $pluginKey);

        // 平台是否已安装（租户可用的前提）
        $platformInstalled = is_file(
            $this->plugin_path . DIRECTORY_SEPARATOR . $pluginKey
            . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'installed.php'
        );

        $items = [];

        // 1. 版本
        $items[] = [
            'label'  => '插件版本',
            'detail' => 'v' . $version,
            'status' => 'success',
        ];

        // 2. 菜单源
        $menuFile = $this->findPluginMenuFile($pluginKey);
        $menuCount = count($this->loadPluginMenuManifest($pluginKey));
        $items[] = [
            'label'  => '菜单源',
            'detail' => $menuFile
                ? str_replace(base_path() . DIRECTORY_SEPARATOR, '', $menuFile) . "（{$menuCount} 个菜单项）"
                : '未定义菜单文件',
            'status' => $menuFile ? 'success' : 'warning',
        ];

        // 3. 平台授权
        $items[] = [
            'label'  => '平台授权',
            'detail' => $platformInstalled ? '平台已安装，可安装到租户' : '平台未安装，无法安装到租户',
            'status' => $platformInstalled ? 'success' : 'error',
        ];

        // 4. 租户类型（隔离模式）
        $items[] = [
            'label'  => '租户类型',
            'detail' => $isolationMode === 'database' ? '库隔离（独立数据库）' : '字段隔离（共享主库）',
            'status' => 'info',
        ];

        // 5. 安装状态
        $items[] = [
            'label'  => '安装状态',
            'detail' => $existing ? '已安装（v' . $existing->version . '）' : '未安装',
            'status' => $existing ? 'warning' : 'info',
        ];

        // 6. 数据库迁移（按隔离模式区分场景）
        $migrationCount = $this->countMigrations($pluginKey);
        if ($isolationMode === 'database') {
            $items[] = [
                'label'  => '数据库迁移',
                'detail' => $migrationCount > 0
                    ? "库隔离：将在租户独立库执行 {$migrationCount} 个迁移文件"
                    : '库隔离：无迁移文件',
                'status' => $migrationCount > 0 ? 'info' : 'success',
            ];
        } else {
            $items[] = [
                'label'  => '数据库迁移',
                'detail' => '字段隔离：跳过迁移（共享表由平台安装时创建）',
                'status' => 'success',
            ];
        }

        // 7. 依赖检查
        if (!empty($pluginInfo['support_app'])) {
            $depCode = $pluginInfo['support_app'];
            $depInstalled = is_file(
                $this->plugin_path . DIRECTORY_SEPARATOR . $depCode
                . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'installed.php'
            );
            $items[] = [
                'label'  => '依赖检查',
                'detail' => '依赖 ' . $depCode . ($depInstalled ? '（已安装）' : '（未安装）'),
                'status' => $depInstalled ? 'success' : 'error',
            ];
        }

        // 8. 版本更新
        if ($existing && version_compare($version, (string)$existing->version, '>')) {
            $items[] = [
                'label'  => '版本更新',
                'detail' => '有可用更新：' . $existing->version . ' → ' . $version,
                'status' => 'warning',
            ];
        }

        return [
            'items'              => $items,
            'isolation_mode'     => $isolationMode,
            'version'            => $version,
            'platform_installed' => (bool)$platformInstalled,
            'installed'          => (bool)$existing,
        ];
    }

    /**
     * 租户安装插件（编排安装流程）
     *
     * 按租户隔离模式区分安装场景：
     *   - 库隔离(database)：安装菜单 + 数据库迁移（在租户独立库上执行）
     *   - 字段隔离(field) ：仅安装菜单（业务表由平台安装时创建，租户共享主库）
     */
    public function install(string $pluginKey, string|int $tenantId, bool $withDatabase = true): \Generator
    {
        $check = $this->canInstall($tenantId, $pluginKey);
        if (!$check['allowed']) {
            yield Sse::error($check['reason'], ['plugin' => $pluginKey]);
            return;
        }

        $pluginInfo = $this->getPluginConfig($pluginKey);
        $version = (string)($pluginInfo['version'] ?? '1.0.0');
        $isolationMode = $this->getIsolationMode($tenantId);

        yield Sse::progress("开始安装插件「{$pluginKey}」v{$version}", 5, [
            'plugin'         => $pluginKey,
            'version'        => $version,
            'isolation_mode' => $isolationMode,
            'with_database'  => $withDatabase,
        ]);

        // 1. 数据库迁移：仅“菜单+数据库”模式执行；库隔离走租户独立库迁移，字段隔离共享表跳过
        if ($withDatabase) {
            if ($isolationMode === 'database') {
                yield Sse::progress('库隔离模式：开始执行数据库迁移', 15, ['plugin' => $pluginKey]);
                try {
                    $migrateResult = $this->runTenantMigrations($pluginKey, (string)$tenantId, 'install', $version);
                    if (!empty($migrateResult['errors'])) {
                        yield Sse::error('数据库迁移失败：' . implode('；', $migrateResult['errors']), ['plugin' => $pluginKey]);
                        return;
                    }
                    yield Sse::progress('数据库迁移完成', 60, ['migrations' => $migrateResult['executed'] ?? 0]);
                } catch (\Throwable $e) {
                    yield Sse::error('数据库迁移失败：' . $e->getMessage(), ['plugin' => $pluginKey]);
                    return;
                }
            } else {
                yield Sse::progress('字段隔离模式：跳过数据库迁移（业务表由平台安装时创建）', 45, ['plugin' => $pluginKey]);
            }
        } else {
            yield Sse::progress('仅安装菜单模式：跳过数据库迁移', 45, ['plugin' => $pluginKey]);
        }

        // 2. 安装插件菜单（两种隔离模式都执行）
        yield Sse::progress('安装插件菜单', 70, ['plugin' => $pluginKey]);
        $menuStats = $this->syncPluginMenu((string)$tenantId, $pluginKey, $version, 'install');
        yield Sse::progress(
            sprintf('插件菜单安装完成（新增 %d，更新 %d）', $menuStats['inserted'] ?? 0, $menuStats['updated'] ?? 0),
            90
        );

        // 3. 记录租户插件信息（含隔离模式）
        $this->createTenantPluginRecord((string)$tenantId, $pluginKey, $version, $isolationMode);
        yield Sse::progress('租户插件信息记录成功', 96);

        yield Sse::completed('插件安装完成', [
            'plugin'         => $pluginKey,
            'version'        => $version,
            'isolation_mode' => $isolationMode,
        ]);
    }

    /**
     * 租户更新插件 (WP6 委托 Orchestrator)
     *
     * 不再走 SSE 流式, 改为非流式同步入口, 复用 PluginLifecycleOrchestrator::updateForTenant.
     * controller 端通过同步响应 + 简单进度轮询 或 JobId 拉取进度.
     */
    public function update(string $pluginKey, string|int $tenantId, ?string $expectedVersion = null): array
    {
        /** @var \app\service\core\plugin\PluginLifecycleOrchestrator $orchestrator */
        $orchestrator = Container::make(\app\service\core\plugin\PluginLifecycleOrchestrator::class);

        $pluginInfo = $this->getPluginConfig($pluginKey);
        $version = $expectedVersion ?? ($pluginInfo['version'] ?? '1.0.0');

        return $orchestrator->updateForTenant($pluginKey, $version, (string)$tenantId);
    }

    /**
     * 三态查询 (WP6)
     *
     * 供前端 granted 模块三态(tab)展示:
     *   - available: 平台已装 + 租户未装
     *   - installed: 租户已装 + 当前版本匹配
     *   - upgradeable: 租户已装 + 版本落后
     */
    public function getInstallStatus(string|int $tenantId, string $pluginKey, ?string $platformVersion = null): string
    {
        $record = $this->dao->findByTenantAndKey($tenantId, $pluginKey);
        if (!$record) {
            return 'available';
        }
        if ((int)$record->status !== 1) {
            return 'available';
        }
        $currentVersion = (string)($record->version ?? '');
        $latest = $platformVersion ?? ($this->getPluginConfig($pluginKey)['version'] ?? null);
        if ($latest && $record->canUpgrade((string)$latest)[0]) {
            return 'upgradeable';
        }
        return 'installed';
    }

    /**
     * 租户卸载插件（编排卸载流程）
     *
     * 按租户隔离模式区分卸载场景：
     *   - 库隔离(database)：回滚数据库迁移 + 清理菜单（在租户独立库上执行）
     *   - 字段隔离(field) ：仅清理菜单（共享表由平台统一管理）
     */
    public function uninstall(string $pluginKey, string|int $tenantId): \Generator
    {
        $existing = $this->dao->findByTenantAndKey($tenantId, $pluginKey);
        if (!$existing) {
            yield Sse::warning('该租户未安装此插件，跳过卸载', ['plugin' => $pluginKey]);
            return;
        }

        $version = (string)($existing->version ?? '1.0.0');
        $isolationMode = $this->getIsolationMode($tenantId);

        yield Sse::progress("开始卸载插件「{$pluginKey}」", 10, [
            'plugin'         => $pluginKey,
            'version'        => $version,
            'isolation_mode' => $isolationMode,
        ]);

        // 1. 库隔离：回滚数据库迁移（租户独立库）；字段隔离：跳过（共享表由平台管理）
        if ($isolationMode === 'database') {
            yield Sse::progress('库隔离模式：回滚数据库迁移', 30, ['plugin' => $pluginKey]);
            try {
                $this->runTenantMigrations($pluginKey, (string)$tenantId, 'uninstall', $version);
                yield Sse::progress('数据库迁移回滚完成', 55, ['plugin' => $pluginKey]);
            } catch (\Throwable $e) {
                yield Sse::progress('数据库迁移回滚失败（继续清理菜单）: ' . $e->getMessage(), 55, ['plugin' => $pluginKey]);
            }
        } else {
            yield Sse::progress('字段隔离模式：跳过数据库回滚（共享表由平台统一管理）', 40, ['plugin' => $pluginKey]);
        }

        // 2. 清理插件菜单
        yield Sse::progress('清理插件菜单', 70, ['plugin' => $pluginKey]);
        $this->clearPluginMenus((string)$tenantId, $pluginKey, $isolationMode);
        yield Sse::progress('插件菜单清理完成', 85, ['plugin' => $pluginKey]);

        // 3. 保留授权记录，仅重置安装状态（与平台授权列表保持一致）
        $existing->fill([
            'status'       => 0,
            'is_purchased' => 0,
            'installed_at' => null,
            'version'      => null,
            'sync_status'  => 'removed',
            'updated_at'   => time(),
        ]);
        $existing->save();
        yield Sse::progress('租户插件记录已重置（保留平台授权，清除安装状态）', 95, ['plugin' => $pluginKey]);

        // 4. 清理运行态记录(saas_tenant_plugin_install)，避免"孤儿记录"导致卡片仍显示已安装
        //    状态查询 TenantPlugin::getStatusAttribute 优先读运行态表，若仅清治理表而漏删运行态，
        //    卡片会一直显示"已安装"（详见 PluginLifecycleOrchestrator::clearTenantInstallRecord）
        $this->clearTenantInstallRecord((string)$tenantId, $pluginKey);
        yield Sse::progress('租户插件运行态记录已清理', 98, ['plugin' => $pluginKey]);

        yield Sse::completed('插件卸载完成', [
            'plugin'         => $pluginKey,
            'isolation_mode' => $isolationMode,
        ]);
    }

    /**
     * 删除租户运行态记录（幂等可重复调用）
     *
     * 占用清单 / 状态查询读的是 saas_tenant_plugin_install(status=1)，若仅删/重置授权表
     * 而漏删运行态表，会留下"孤儿记录"并继续显示在列表里。
     */
    protected function clearTenantInstallRecord(string $tenantId, string $pluginKey): void
    {
        try {
            TenantPluginInstall::where('tenant_id', $tenantId)
                ->where('plugin_key', $pluginKey)
                ->delete();
        } catch (\Throwable $e) {
            // 清理失败不阻断卸载
        }
    }

    /**
     * 获取隔离模式
     *
     * 按租户维度区分：优先读租户记录 database_mode（库隔离/字段隔离），
     * 兜底用请求上下文的隔离模式，最后回退全局默认配置。
     */
    private function getIsolationMode(string|int|null $tenantId = null): string
    {
        if ($tenantId !== null) {
            $mode = Tenant::withoutGlobalScopes()
                ->where('id', (string)$tenantId)
                ->value('database_mode');
            if (in_array($mode, ['field', 'database'], true)) {
                return $mode;
            }
        }

        $ctxMode = TenantContext::getIsolationMode();
        if (in_array($ctxMode, ['field', 'database'], true)) {
            return $ctxMode;
        }

        return config('tenant.default_mode', 'field');
    }

    /**
     * 统计插件迁移文件数量
     */
    private function countMigrations(string $pluginKey): int
    {
        $migrationDir = $this->plugin_path . DIRECTORY_SEPARATOR
            . $pluginKey . DIRECTORY_SEPARATOR
            . 'resource' . DIRECTORY_SEPARATOR
            . 'database' . DIRECTORY_SEPARATOR
            . 'migrations';

        if (!is_dir($migrationDir)) {
            return 0;
        }

        $files = scandir($migrationDir);
        $count = 0;
        foreach ($files as $file) {
            if ($file !== '.' && $file !== '..' && pathinfo($file, PATHINFO_EXTENSION) === 'php') {
                $count++;
            }
        }

        return $count;
    }

    // ============================================================
    // 租户插件配置 / 升级治理
    // ============================================================

    /**
     * 读取租户插件配置
     *
     * 配置不再写在平台主表 md_saas_tenant_plugin.config, 而是按隔离模式落到租户侧
     * 配置存储(见 TenantPluginConfigService): field→主库 sys_config, database→租户库 sys_config。
     */
    public function getTenantPluginConfig(string|int $tenantId, string $pluginKey): array
    {
        /** @var TenantPluginConfigService $configService */
        $configService = Container::make(TenantPluginConfigService::class);
        return $configService->get($tenantId, $pluginKey);
    }

    /**
     * 保存租户插件配置
     */
    public function saveTenantPluginConfig(string|int $tenantId, string $pluginKey, array $config): void
    {
        /** @var TenantPluginConfigService $configService */
        $configService = Container::make(TenantPluginConfigService::class);
        $configService->set($tenantId, $pluginKey, $config);
    }

    /**
     * 租户端忽略某个版本的升级
     *
     * 忽略后该版本不再出现在"可升级"提示中, 批量升级也会跳过该版本。
     * 传 null 表示取消忽略。
     */
    public function ignoreUpgrade(string|int $tenantId, string $pluginKey, ?string $version): void
    {
        $record = $this->dao->findByTenantAndKey($tenantId, $pluginKey);
        if (!$record) {
            throw new PluginException('插件记录不存在');
        }

        $record->fill([
            'ignored_version' => $version,
            'updated_at'      => time(),
        ]);
        $record->save();
    }

    /**
     * 平台端设置该租户/插件是否允许升级
     */
    public function setUpgradePolicy(string|int $tenantId, string $pluginKey, bool $allowed): void
    {
        $record = $this->dao->findByTenantAndKey($tenantId, $pluginKey);
        if (!$record) {
            throw new PluginException('插件记录不存在');
        }

        $record->fill([
            'allow_upgrade' => $allowed ? 1 : 0,
            'updated_at'    => time(),
        ]);
        $record->save();
    }

    // ============================================================
    // 安装/卸载辅助（按隔离模式区分场景）
    // ============================================================

    /**
     * 在租户独立库上执行插件数据库迁移（库隔离模式）
     *
     * 优先使用插件 Install 类（若继承 PluginInstall，自带迁移/种子执行），
     * 否则兜底直接执行插件 resource/database/migrations 下的迁移文件。
     *
     * @return array{executed:int, errors:array}
     */
    /**
     * 在租户侧执行插件迁移（按隔离模式）
     *
     * 委托 TenantPluginMigrationService 统一处理, 与 Orchestrator/批量/队列保持同一套行为:
     *   - database: 切到租户独立库执行(插件 Install 类优先, 否则跑 migrations 文件)
     *   - field   : 跳过(共享主库, 业务表由平台安装插件时统一创建)
     */
    protected function runTenantMigrations(string $pluginKey, string $tenantId, string $action, string $version, ?string $oldVersion = null): array
    {
        /** @var TenantPluginMigrationService $migrator */
        $migrator = Container::make(TenantPluginMigrationService::class);

        return $migrator->run(
            $pluginKey,
            $tenantId,
            $action,
            $version,
            $this->getIsolationMode($tenantId),
            $oldVersion
        );
    }

    /**
     * 同步插件菜单到租户菜单表（安装/卸载）
     */
    protected function syncPluginMenu(string $tenantId, string $pluginKey, string $version, string $action = 'install'): array
    {
        $manifest = $this->loadPluginMenuManifest($pluginKey);
        if (empty($manifest)) {
            return ['inserted' => 0, 'updated' => 0, 'disabled' => 0];
        }

        /** @var TenantMenuSyncService $menuService */
        $menuService = Container::make(TenantMenuSyncService::class);
        $diff = $menuService->diff($tenantId, $pluginKey, $manifest);
        $stats = $menuService->apply($tenantId, $pluginKey, $diff, $version);

        return $stats;
    }

    /**
     * 查找插件菜单文件路径（resource/data/menu/admin.php 或 resource/menu/admin.php）
     */
    protected function findPluginMenuFile(string $pluginKey): ?string
    {
        $base = $this->plugin_path . DIRECTORY_SEPARATOR
            . $pluginKey . DIRECTORY_SEPARATOR
            . 'resource';

        $candidates = [
            $base . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'menu' . DIRECTORY_SEPARATOR . 'admin.php',
            $base . DIRECTORY_SEPARATOR . 'menu' . DIRECTORY_SEPARATOR . 'admin.php',
        ];

        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * 加载插件菜单清单（resource/data/menu/admin.php 或 resource/menu/admin.php）
     *
     * @return array<int, array> 展平后的菜单项(含 code)
     */
    protected function loadPluginMenuManifest(string $pluginKey): array
    {
        $file = $this->findPluginMenuFile($pluginKey);
        if (!$file) {
            return [];
        }

        $tree = include $file;
        if (!is_array($tree)) {
            return [];
        }

        $out = [];
        $index = 0;
        $this->flattenMenuItems($tree, $pluginKey, 1, $index, $out);
        return $out;
    }

    /**
     * 嵌套菜单 → 展平（与 Orchestrator flattenMenu 保持一致）
     *
     * 子菜单通过 pid_code 记录父级 code，供 TenantMenuSyncService 解析父级 ID。
     */
    protected function flattenMenuItems(array $items, string $pluginKey, int $level, int &$index, array &$out, ?string $parentCode = null): void
    {
        foreach ($items as $item) {
            $index++;
            $code = $item['code'] ?? $this->autoCode($item, $pluginKey, $level, $index);
            $out[] = [
                'code'       => $code,
                'title'      => (string)($item['name'] ?? $item['title'] ?? ''),
                'path'       => (string)($item['path'] ?? ''),
                'component'  => (string)($item['component'] ?? ''),
                'icon'       => (string)($item['icon'] ?? ''),
                'sort'       => (int)($item['sort'] ?? 0),
                'type'       => (int)($item['type'] ?? ($level === 1 ? 1 : 2)),
                'is_show'    => (int)($item['is_show'] ?? 1),
                'is_sync'    => (int)($item['is_sync'] ?? 0),
                'pid'        => 0,
                'pid_code'   => $parentCode,
                'level'      => $level,
                'app'        => $item['app'] ?? 'admin',
                '_plugin_key' => $pluginKey,
            ];

            if (!empty($item['children']) && is_array($item['children'])) {
                $this->flattenMenuItems($item['children'], $pluginKey, $level + 1, $index, $out, $code);
            }
        }
    }

    /**
     * 菜单 code 自动生成（与 MenuTrait::normalizeMenuItem 一致）
     */
    protected function autoCode(array $item, string $plugin, int $level, int $index): string
    {
        $path = (string)($item['path'] ?? '');
        if ($path !== '') {
            return str_replace('/', ':', trim($path, '/'));
        }
        return $plugin . ':level' . $level . '_' . $index;
    }

    /**
     * 创建/更新租户插件记录（含隔离模式）
     *
     * 同时写入授权治理表(saas_tenant_plugin)和运行态表(saas_tenant_plugin_install),
     * 保证矩阵、统计等直接读取运行态表的逻辑能实时反映安装结果。
     */
    protected function createTenantPluginRecord(string $tenantId, string $pluginKey, string $version, string $isolationMode): void
    {
        $existing = $this->dao->findByTenantAndKey($tenantId, $pluginKey);
        $now = time();

        $runtimeData = [
            'tenant_id'      => $tenantId,
            'plugin_key'     => $pluginKey,
            'version'        => $version,
            'status'         => 1,
            'installed_at'   => $now,
            'sync_status'    => 'success',
            'isolation_mode' => $isolationMode,
            'updated_at'     => $now,
        ];

        if (!$existing) {
            $authData = [
                'id'             => Snowflake::generate(),
                'tenant_id'      => $tenantId,
                'plugin_key'     => $pluginKey,
                'version'        => $version,
                'status'         => 1,
                'is_purchased'   => 1,
                'installed_at'   => $now,
                'sync_status'    => 'success',
                'isolation_mode' => $isolationMode,
                'created_at'     => $now,
                'updated_at'     => $now,
            ];
            $authRecord = $this->dao->getModel()->create($authData);
            TenantPluginInstall::query()->create(array_merge($runtimeData, [
                'id'         => $authRecord->id,
                'created_at' => $now,
            ]));
            return;
        }

        $existing->fill([
            'version'        => $version,
            'status'         => 1,
            'is_purchased'   => 1,
            'installed_at'   => $now,
            'sync_status'    => 'success',
            'isolation_mode' => $isolationMode,
            'updated_at'     => $now,
        ]);
        $existing->save();

        $runtime = $existing->install;
        if (!$runtime) {
            TenantPluginInstall::query()->create(array_merge($runtimeData, [
                'id'         => $existing->id,
                'created_at' => $now,
            ]));
        } else {
            $runtime->fill($runtimeData);
            $runtime->save();
        }
    }

    /**
     * 清理插件在租户菜单表中的菜单
     *
     * - 库隔离(database)：在租户独立库中按 source=plugin:{key} 清理
     * - 字段隔离(field) ：主库中按 tenant_id + source=plugin:{key} 清理
     */
    /**
     * 清理插件在租户侧的菜单与配置
     *
     * 两个服务内部均按【该租户】的隔离模式解析连接(读 Tenant.database_mode):
     *   database → 清理租户独立库的 sys_menu / sys_config
     *   field    → 清理主库中该 tenant_id 的数据
     * 卸载时租户配置一并清理, 避免租户库残留孤儿配置。
     */
    protected function clearPluginMenus(string $tenantId, string $pluginKey, string $isolationMode): void
    {
        try {
            /** @var TenantMenuSyncService $menuService */
            $menuService = Container::make(TenantMenuSyncService::class);
            $menuService->clear($tenantId, $pluginKey);
        } catch (\Throwable $e) {
            // 菜单清理失败不阻断卸载
        }

        try {
            /** @var TenantPluginConfigService $configService */
            $configService = Container::make(TenantPluginConfigService::class);
            $configService->delete($tenantId, $pluginKey);
        } catch (\Throwable $e) {
            // 配置清理失败不阻断卸载
        }
    }
}
