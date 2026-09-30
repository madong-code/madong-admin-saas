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

namespace app\service\core\plugin;

use app\dao\plugin\TenantPluginDao;
use app\event\plugin\PluginInstalled;
use app\event\plugin\PluginInstalling;
use app\event\plugin\PluginUninstalled;
use app\event\plugin\PluginUninstalling;
use app\event\plugin\PluginUpdated;
use app\event\plugin\PluginUpdating;
use app\model\plugin\TenantPlugin;
use app\model\tenant\Tenant;
use core\business\tenant\context\TenantContext;
use core\foundation\base\BaseService;
use core\io\uuid\Snowflake;
use support\Container;
use support\Log;

/**
 * 插件生命周期编排器 (WP5 完整实现)
 *
 * 定位:
 *   PluginInstallService / PluginUninstallService 仍负责"前端 SSE 流式反馈",
 *   本类是它们的下游"非 SSE 同步入口", 被以下场景调用:
 *     - 后台/平台 controller 直接驱动(非流式)
 *     - PluginBatchExecutor(WP4) 逐租户调用
 *     - PluginSyncJobConsumer(WP4) 队列消费
 *
 * 七入口(对外):
 *   - installPlatform()        平台层安装
 *   - updatePlatform()         平台层更新
 *   - uninstallPlatform()      平台层卸载
 *   - installForTenant()       租户层安装
 *   - updateForTenant()        租户层更新
 *   - uninstallForTenant()     租户层卸载
 *   - cascadeUninstall()       强制级联卸载(先逐租户 → 后平台, 用于"占用阻断"绕过)
 *
 * 单体降级:
 *   isSingleMode() === true 时, *ForTenant() 与 *Platform() 行为完全等价:
 *   - 无 tenant_id 概念
 *   - 菜单/迁移直接落主库
 *   - 不写 saas_tenant_plugin 记录
 *
 * 事件载荷:
 *   所有 install/update/uninstall 都派发 context='orchestrator' 的事件,
 *   防止 TenantPluginSyncListener 重复扇出 (见 WP2 幂等契约).
 *
 * 状态机:
 *   pending → running → (success | failed)
 *   字段落点: TenantPlugin.sync_status
 *   失败时强制写回 failed, 抛出前的"半成功"状态由调用方 catch 决定是否回滚
 *
 * @author Mr.April
 * @since  1.0
 */
class PluginLifecycleOrchestrator extends BaseService
{
    /** @var string 事件上下文标识(供 TenantPluginSyncListener 幂等跳过) */
    public const EVENT_CONTEXT = 'orchestrator';

    // ============================================================
    // 七入口
    // ============================================================

    /**
     * 平台层安装
     *
     * @return array 执行结果 { stage, code, version, isolation_mode, mode }
     */
    public function installPlatform(string $code, string $version, array $opts = []): array
    {
        $mode = $this->mode();
        $isolationMode = $this->resolveIsolationMode();

        try {
            (new PluginInstalling($code, $version, [], self::EVENT_CONTEXT))->dispatch();
            $this->executePluginMethodSilent($code, 'install', $version);
            (new PluginInstalled($code, $version, [], self::EVENT_CONTEXT))->dispatch();

            $this->logInfo('installPlatform ok', compact('code', 'version', 'mode'));

            return [
                'stage'          => 'install',
                'scope'          => 'platform',
                'code'           => $code,
                'version'        => $version,
                'isolation_mode' => $isolationMode,
                'mode'           => $mode,
            ];
        } catch (\Throwable $e) {
            $this->logError('installPlatform failed', $e);
            throw $e;
        }
    }

    /**
     * 平台层更新
     */
    public function updatePlatform(string $code, string $fromVersion, string $version, array $opts = []): array
    {
        $mode = $this->mode();

        try {
            (new PluginUpdating($code, $version, ['from' => $fromVersion], self::EVENT_CONTEXT))->dispatch();
            $this->executePluginMethodSilent($code, 'update', $version, $fromVersion);
            (new PluginUpdated($code, $version, ['from' => $fromVersion], self::EVENT_CONTEXT))->dispatch();

            $this->logInfo('updatePlatform ok', compact('code', 'fromVersion', 'version', 'mode'));

            return [
                'stage'   => 'update',
                'scope'   => 'platform',
                'code'    => $code,
                'from'    => $fromVersion,
                'version' => $version,
                'mode'    => $mode,
            ];
        } catch (\Throwable $e) {
            $this->logError('updatePlatform failed', $e);
            throw $e;
        }
    }

    /**
     * 平台层卸载
     *
     * 安全门: 默认要求"无占用租户" (可 force=true 绕过 → cascadeUninstall)
     */
    public function uninstallPlatform(string $code, string $version, array $opts = []): array
    {
        $force = (bool)($opts['force'] ?? false);
        $blockWhenOccupied = (bool)config('plugin.uninstall.block_when_occupied', true);

        if (!$force && $blockWhenOccupied) {
            $this->guardNoOccupants($code);
        }

        $mode = $this->mode();

        try {
            (new PluginUninstalling($code, $version, ['force' => $force], self::EVENT_CONTEXT))->dispatch();
            $this->executePluginMethodSilent($code, 'uninstall', $version);
            (new PluginUninstalled($code, $version, ['force' => $force], self::EVENT_CONTEXT))->dispatch();

            $this->logInfo('uninstallPlatform ok', compact('code', 'version', 'force', 'mode'));

            return [
                'stage' => 'uninstall',
                'scope' => 'platform',
                'code'  => $code,
                'version' => $version,
                'force' => $force,
                'mode'  => $mode,
            ];
        } catch (\Throwable $e) {
            $this->logError('uninstallPlatform failed', $e);
            throw $e;
        }
    }

    /**
     * 租户层安装
     */
    public function installForTenant(string $code, string $version, string $tenantId, array $opts = [], ?callable $progressCb = null): array
    {
        $this->assertMultiMode(__FUNCTION__);
        $this->markTenantContext((string)$tenantId);

        /** @var TenantPluginDao $dao */
        $dao = Container::make(TenantPluginDao::class);
        $existing = $dao->findByTenantAndKey($tenantId, $code);

        // 幂等: 已存在 enabled 记录直接返回
        if ($existing && (int)$existing->status === 1) {
            $this->logInfo('installForTenant skipped (already installed)', compact('code', 'tenantId'));
            return [
                'stage' => 'install',
                'scope' => 'tenant',
                'code'  => $code,
                'tenant_id' => (string)$tenantId,
                'skipped' => true,
            ];
        }

        $mode = $this->resolveIsolationMode((string)$tenantId);
        $this->upsertTenantRecord($tenantId, $code, $version, 'running');

        try {
            (new PluginInstalling($code, $version, ['tenant_id' => $tenantId], self::EVENT_CONTEXT))->dispatch();

            // 库隔离: 在租户独立库建表; 字段隔离: 跳过(共享主库, 业务表由平台安装时创建)
            $this->notifyStep($progressCb, '开始执行数据库迁移文件');
            $this->runMigrations($code, (string)$tenantId, 'install', $version, $mode);
            $this->notifyStep($progressCb, '数据库迁移文件执行完成');

            $this->notifyStep($progressCb, '执行安装钩子');
            $this->executePluginMethodSilent($code, 'install', $version);
            $this->notifyStep($progressCb, '安装钩子执行完成');

            $this->notifyStep($progressCb, '开始导入插件菜单');
            $this->applyMenuSync($code, $version, (string)$tenantId, 'install');
            $this->notifyStep($progressCb, '插件菜单导入完成');

            (new PluginInstalled($code, $version, ['tenant_id' => $tenantId], self::EVENT_CONTEXT))->dispatch();

            $this->upsertTenantRecord($tenantId, $code, $version, 'success');

            return [
                'stage' => 'install',
                'scope' => 'tenant',
                'code'  => $code,
                'tenant_id' => (string)$tenantId,
                'version' => $version,
            ];
        } catch (\Throwable $e) {
            $this->upsertTenantRecord($tenantId, $code, $version, 'failed', $e->getMessage());
            $this->logError('installForTenant failed', $e, compact('code', 'tenantId'));
            throw $e;
        }
    }

    /**
     * 租户层更新
     */
    public function updateForTenant(string $code, string $version, string $tenantId, array $opts = [], ?callable $progressCb = null): array
    {
        $this->assertMultiMode(__FUNCTION__);
        $this->markTenantContext((string)$tenantId);

        /** @var TenantPluginDao $dao */
        $dao = Container::make(TenantPluginDao::class);
        $existing = $dao->findByTenantAndKey($tenantId, $code);

        if (!$existing) {
            // 未安装: 升级前自动 install
            return $this->installForTenant($code, $version, $tenantId, $opts, $progressCb);
        }

        $fromVersion = (string)($existing->version ?? '');

        // 升级治理: 平台开关(allow_upgrade) + 租户忽略版本(ignored_version) + 版本比较
        [$allowed, $reasonCode] = $existing->canUpgrade($version);
        if (!$allowed) {
            $this->logInfo("updateForTenant skipped ({$reasonCode})", compact('code', 'tenantId', 'fromVersion'));
            return [
                'stage'     => 'update',
                'scope'     => 'tenant',
                'code'      => $code,
                'tenant_id' => (string)$tenantId,
                'from'      => $fromVersion,
                'skipped'   => true,
                'reason'    => $reasonCode,
            ];
        }

        $mode = $this->resolveIsolationMode((string)$tenantId);
        $this->upsertTenantRecord($tenantId, $code, $version, 'running');

        try {
            (new PluginUpdating($code, $version, ['tenant_id' => $tenantId, 'from' => $fromVersion], self::EVENT_CONTEXT))->dispatch();

            // 库隔离: 在租户独立库执行增量迁移; 字段隔离: 跳过(共享表由平台维护)
            $this->notifyStep($progressCb, '开始执行数据库迁移文件');
            $this->runMigrations($code, (string)$tenantId, 'update', $version, $mode, $fromVersion);
            $this->notifyStep($progressCb, '数据库迁移文件执行完成');

            $this->notifyStep($progressCb, '执行升级钩子');
            $this->executePluginMethodSilent($code, 'update', $version, $fromVersion);
            $this->notifyStep($progressCb, '升级钩子执行完成');

            $this->notifyStep($progressCb, '开始更新插件菜单');
            $this->applyMenuSync($code, $version, (string)$tenantId, 'update');
            $this->notifyStep($progressCb, '插件菜单更新完成');

            (new PluginUpdated($code, $version, ['tenant_id' => $tenantId, 'from' => $fromVersion], self::EVENT_CONTEXT))->dispatch();

            $this->upsertTenantRecord($tenantId, $code, $version, 'success');

            return [
                'stage' => 'update',
                'scope' => 'tenant',
                'code'  => $code,
                'tenant_id' => (string)$tenantId,
                'from' => $fromVersion,
                'version' => $version,
            ];
        } catch (\Throwable $e) {
            $this->upsertTenantRecord($tenantId, $code, $version, 'failed', $e->getMessage());
            $this->logError('updateForTenant failed', $e, compact('code', 'tenantId'));
            throw $e;
        }
    }

    /**
     * 租户层卸载
     */
    public function uninstallForTenant(string $code, string $version, string $tenantId, array $opts = [], ?callable $progressCb = null): array
    {
        $this->assertMultiMode(__FUNCTION__);
        $this->markTenantContext((string)$tenantId);

        /** @var TenantPluginDao $dao */
        $dao = Container::make(TenantPluginDao::class);
        $existing = $dao->findByTenantAndKey($tenantId, $code);

        // 幂等: 授权记录不存在时, 仍要清理可能残留的运行态孤儿记录
        if (!$existing) {
            $this->clearTenantInstallRecord((string)$tenantId, $code);
            return [
                'stage' => 'uninstall',
                'scope' => 'tenant',
                'code'  => $code,
                'tenant_id' => (string)$tenantId,
                'skipped' => true,
            ];
        }

        $this->upsertTenantRecord($tenantId, $code, $version, 'running');

        try {
            // 卸载前事件: 尽力而为, 失败仅记录日志, 绝不阻断卸载主流程(否则事件/监听器异常会跳过运行态删除)
            try {
                (new PluginUninstalling($code, $version, ['tenant_id' => $tenantId], self::EVENT_CONTEXT))->dispatch();
            } catch (\Throwable $e) {
                $this->logError('plugin uninstalling event failed (ignored)', $e, compact('code', 'tenantId'));
            }

            // 数据库操作策略(决定本租户卸载是否触碰库):
            //   - 字段隔离(field) : 共享主库, 业务表由平台维护 → 绝不操作数据库, 仅清菜单
            //   - 库隔离(database): 由 info.uninstall.drop_tables 决定
            //                        true = 回滚迁移(删表) + 菜单; false = 仅清菜单(保留数据)
            $mode       = $this->resolveIsolationMode((string)$tenantId);
            $dropTables = $this->readDropTables($code);
            $touchDb    = ($mode === 'database') && $dropTables;

            if ($touchDb) {
                // 库隔离 + 允许删表: 走迁移回滚(内部正确设置租户库连接并调用插件 uninstall 钩子)
                try {
                    $this->notifyStep($progressCb, '开始回滚数据库迁移文件');
                    $this->runMigrations($code, (string)$tenantId, 'uninstall', $version, $mode);
                    $this->notifyStep($progressCb, '数据库迁移文件回滚完成');
                } catch (\Throwable $e) {
                    $this->logError('uninstall migrations failed, continue cleanup', $e, compact('code', 'tenantId'));
                }
            } else {
                $this->notifyStep(
                    $progressCb,
                    $mode === 'database'
                        ? 'drop_tables=false, 仅清理菜单(保留数据库)'
                        : '字段隔离模式, 仅清理菜单(不操作数据库)'
                );
            }

            // 菜单清理: 尽力而为, 失败不阻断后续核心清理(否则菜单同步一旦抛异常,
            // 会跳过下面的运行态删除, 导致运行态记录残留、UI 仍显示"已安装")。
            $this->notifyStep($progressCb, '开始清理插件菜单');
            try {
                $this->applyMenuSync($code, $version, (string)$tenantId, 'uninstall');
                $this->notifyStep($progressCb, '插件菜单清理完成');
            } catch (\Throwable $e) {
                $this->logError('clear tenant menus failed (ignored)', $e, compact('code', 'tenantId'));
            }

            // 清理残留产物(菜单/配置): 尽力而为, 失败不阻断后续核心清理。
            // 否则 TenantPluginConfigService::delete 等在租户上下文连名未配置时会抛异常,
            // 跳过 clearTenantInstallRecord, 导致运行态残留、UI 仍显示"已安装"。
            try {
                $this->clearTenantArtifacts((string)$tenantId, $code);
            } catch (\Throwable $e) {
                $this->logError('clear tenant artifacts failed (ignored)', $e, compact('code', 'tenantId'));
            }

            // 卸载后事件: 尽力而为, 失败仅记录日志
            try {
                (new PluginUninstalled($code, $version, ['tenant_id' => $tenantId], self::EVENT_CONTEXT))->dispatch();
            } catch (\Throwable $e) {
                $this->logError('plugin uninstalled event failed (ignored)', $e, compact('code', 'tenantId'));
            }

            // 仅清理运行态记录(saas_tenant_plugin_install), 保留授权账本(saas_tenant_plugin)。
            // 注意: 不要 $existing->delete() —— 授权是平台对租户的购买授权, 卸载运行态不应抹除,
            // 否则租户在"我的应用"里将找不到该插件、只能平台重新安装。
            $this->clearTenantInstallRecord((string)$tenantId, $code);

            // 清空授权表上的运行态冗余字段(installed_at/version 等),
            // 让 getMatrix 的兜底判定正确识别为"未安装"。授权状态字段(auth_status/status/is_purchased)一律保留。
            // 注意: isolation_mode 列 NOT NULL(默认 field), 不能置 null, 否则整段 update 违反约束抛异常,
            // 导致 installed_at 清不掉、UI 仍显示"已安装可卸载" → 卸载永久失败。
            // 卸载后重置为该租户实际隔离模式(字段合法且保留语义)。
            $existing->update([
                'installed_at'       => null,
                'version'            => null,
                'sync_status'        => 'pending',
                'isolation_mode'     => $mode,
                'deprecated_version' => null,
            ]);

            return [
                'stage' => 'uninstall',
                'scope' => 'tenant',
                'code'  => $code,
                'tenant_id' => (string)$tenantId,
                'version' => $version,
            ];
        } catch (\Throwable $e) {
            $this->upsertTenantRecord($tenantId, $code, $version, 'failed', $e->getMessage());
            $this->logError('uninstallForTenant failed', $e, compact('code', 'tenantId'));
            throw $e;
        }
    }

    /**
     * 强制级联卸载(批量租户侧卸载)
     *
     * 流程: 逐租户 cleanup(菜单 + 数据 + 占用记录)
     * 即使中途某租户失败, 也会继续完成其他租户清理
     *
     * 重要: 本方法【只清理租户侧】, 永远不卸载平台层插件包 (不删除 installed.php)。
     *   - $opts['tenant_ids'] 非空: 仅处理指定租户
     *   - 留空: 清理该插件【全部】已安装租户(仍保留平台层安装)
     *
     * 平台层卸载请走 uninstallPlatform() / PluginUninstallService::uninstall($code, null),
     * 避免"我的应用/分发中心"在批量卸载时误把平台插件包一并移除。
     */
    public function cascadeUninstall(string $code, string $version, array $opts = []): array
    {
        $this->assertMultiMode(__FUNCTION__);

        /** @var TenantPluginDao $dao */
        $dao = Container::make(TenantPluginDao::class);

        // 目标租户集合: 与租户端 /adminapi/tenant/plugin/{key}/uninstall 逻辑一致(幂等逐租户执行), 仅支持批量。
        //   - 指定 tenant_ids: 直接按指定租户执行, 不因运行态 status 预过滤(否则"已卸载但 UI 仍判已安装"的租户会被跳过);
        //   - 未指定: 覆盖全部"判定为已安装"的租户 —— 运行态表(status=1) ∪ 授权表兜底(status=1 且 installed_at 非空),
        //     与平台矩阵 getMatrix / authList 的判定同源, 保证卸载后 UI 不再显示"可卸载"。
        $tenantIdFilter = $opts['tenant_ids'] ?? [];
        $selective      = !empty($tenantIdFilter);
        $targets        = [];

        if ($selective) {
            foreach (array_map('strval', $tenantIdFilter) as $tid) {
                if ($tid !== '') {
                    $targets[] = $tid;
                }
            }
        } else {
            foreach ($dao->getInstalledTenants($code) as $row) {
                $tid = (string)($row['tenant_id'] ?? '');
                if ($tid !== '') {
                    $targets[] = $tid;
                }
            }
            // 过渡期脏数据兜底: 授权表含运行态字段但运行态表缺失/未启用时, UI 仍按 auth 兜底显示"已安装"
            foreach (TenantPlugin::query()
                         ->where('plugin_key', $code)
                         ->where('status', 1)
                         ->whereNotNull('installed_at')
                         ->pluck('tenant_id') as $tid) {
                if ($tid !== '') {
                    $targets[] = (string)$tid;
                }
            }
            $targets = array_values(array_unique($targets));
        }

        $tenantResults = [];
        $tenantFailed = 0;

        foreach ($targets as $tid) {
            try {
                $this->markTenantContext($tid);
                $this->uninstallForTenant($code, $version, $tid, $opts);
                $tenantResults[$tid] = 'success';
            } catch (\Throwable $e) {
                $tenantResults[$tid] = 'failed: ' . $e->getMessage();
                $tenantFailed++;
            }
        }

        return [
            'stage'          => 'cascade_uninstall',
            'code'           => $code,
            'version'        => $version,
            'selective'      => $selective,
            'scope'          => 'tenant', // 标记: 仅租户侧, 未卸载平台层
            'processed'      => count($tenantResults),
            'tenants'        => $tenantResults,
            'tenant_failed'  => $tenantFailed,
        ];
    }

    // ============================================================
    // 内部
    // ============================================================

    /**
     * 单体模式判定
     */
    public function isSingleMode(): bool
    {
        return TenantContext::isSingleMode();
    }

    /**
     * 调 PluginBaseService::executePluginMethod (CLI 静默同步路径, 抛异常)
     */
    protected function executePluginMethodSilent(string $code, string $action, ?string $version = null, ?string $oldVersion = null): void
    {
        // 走 PluginBaseService 提供的同步入口, silent=true → 失败统一抛
        /** @var \app\service\core\plugin\PluginBaseService $runner */
        $runner = new class extends PluginBaseService {
            public function run(string $code, string $action, ?string $version, ?string $oldVersion): bool
            {
                return $this->executePluginMethod($code, $action, $version, $oldVersion, false);
            }
        };

        $runner->run($code, $action, $version, $oldVersion);
    }

    /**
     * 按隔离模式执行租户侧迁移
     *
     * 抽自 TenantPluginService::runTenantMigrations, 供 install/update/uninstall 以及
     * 批量执行器、队列消费者共用, 保证"批量/队列"与"单租户在线安装"行为一致。
     */
    protected function runMigrations(
        string $code,
        string $tenantId,
        string $action,
        string $version,
        string $mode,
        ?string $oldVersion = null
    ): array {
        /** @var TenantPluginMigrationService $migrator */
        $migrator = Container::make(TenantPluginMigrationService::class);
        $result   = $migrator->run($code, $tenantId, $action, $version, $mode, $oldVersion);

        if (!empty($result['errors'])) {
            throw new \RuntimeException('插件迁移失败: ' . implode('；', $result['errors']));
        }

        $this->logInfo("runMigrations({$action}) ok", [
            'code'      => $code,
            'tenant_id' => $tenantId,
            'mode'      => $mode,
            'executed'  => $result['executed'] ?? 0,
            'skipped'   => $result['skipped'] ?? false,
        ]);

        return $result;
    }

    /**
     * 清理租户侧插件痕迹(菜单 + 配置)
     *
     * 菜单服务与配置服务内部会按【该租户】的隔离模式解析连接:
     *   database → 清理租户独立库的 sys_menu / sys_config
     *   field    → 清理主库中该 tenant_id 的数据
     * 因此批量/队列场景下每个租户只会清掉自己那份, 不会互相污染。
     */
    protected function clearTenantArtifacts(string $tenantId, string $code): void
    {
        try {
            /** @var TenantMenuSyncService $menuSync */
            $menuSync = Container::make(TenantMenuSyncService::class);
            $menuSync->clear($tenantId, $code);
        } catch (\Throwable $e) {
            $this->logError('clear plugin menus failed', $e, compact('code', 'tenantId'));
        }

        try {
            /** @var TenantPluginConfigService $configService */
            $configService = Container::make(TenantPluginConfigService::class);
            $configService->delete($tenantId, $code);
        } catch (\Throwable $e) {
            $this->logError('clear plugin config failed', $e, compact('code', 'tenantId'));
        }
    }

    /**
     * 删除租户运行态记录(幂等可重复调用)
     *
     * 占用清单接口 getOccupying 读的是 saas_tenant_plugin_install(status=1),
     * 若仅删授权表而漏删运行态表, 会留下"孤儿记录"并继续显示在列表里。
     */
    protected function clearTenantInstallRecord(string $tenantId, string $code): void
    {
        try {
            \app\model\plugin\TenantPluginInstall::where('tenant_id', $tenantId)
                ->where('plugin_key', $code)
                ->delete();
        } catch (\Throwable $e) {
            $this->logError('delete TenantPluginInstall failed', $e, compact('code', 'tenantId'));
        }
    }

    /**
     * 读取插件 info.php 的 uninstall.drop_tables 配置
     *
     * 库隔离(database)模式下, 决定租户卸载是否回滚数据库(删表)。
     * 字段隔离(field)模式永远不依赖此项(绝不操作库)。
     */
    protected function readDropTables(string $code): bool
    {
        $configFile = base_path('plugin' . DIRECTORY_SEPARATOR . $code . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'info.php');
        if (!is_file($configFile)) {
            return false;
        }
        $info = include $configFile;
        return !empty($info['uninstall']['drop_tables']);
    }

    /**
     * 应用菜单三分类同步
     */
    protected function applyMenuSync(string $code, string $version, string $tenantId, string $action): void
    {
        $manifest = $this->loadMenuManifest($code, $action);
        if (empty($manifest)) {
            return;
        }

        /** @var TenantMenuSyncService $menu */
        $menu = Container::make(TenantMenuSyncService::class);
        $diff = $menu->diff($tenantId, $code, $manifest);
        $menu->apply($tenantId, $code, $diff, $version);
    }

    /**
     * 同步插件菜单到指定租户（对外公开，供在线安装 PluginInstallService 在租户安装后调用）
     *
     * 与 installForTenant 内部 applyMenuSync 复用同一套"加载清单 → diff → apply"逻辑,
     * 保证在线安装与编排器/队列安装两条路径的菜单落库行为一致。
     */
    public function syncTenantMenu(string $code, string $version, string $tenantId): void
    {
        $this->applyMenuSync($code, $version, $tenantId, 'install');
    }

    /**
     * 清理指定租户的插件菜单（对外公开，供在线卸载 PluginUninstallService 在租户卸载后调用）
     *
     * 与 uninstallForTenant 内部 applyMenuSync('uninstall') 复用同一逻辑(按 source 软禁用/清理 sys_menu)。
     */
    public function clearTenantMenu(string $code, string $version, string $tenantId): void
    {
        $this->applyMenuSync($code, $version, $tenantId, 'uninstall');
    }

    /**
     * 加载插件菜单清单
     *
     * - 平台模式: 读 plugin/{code}/resource/menu/admin.php
     * - 租户模式: 读 plugin/{code}/resource/menu/admin.php (manifest 与平台共用)
     *
     * @return array<int, array> 展平后的菜单项(已含 code)
     */
    protected function loadMenuManifest(string $code, string $action): array
    {
        $base = base_path('plugin' . DIRECTORY_SEPARATOR . $code . DIRECTORY_SEPARATOR . 'resource');

        // 兼容两种菜单目录约定(与 TenantPluginService::findPluginMenuFile 保持一致):
        //   1. resource/data/menu/admin.php  —— 插件脚手架生成路径
        //   2. resource/menu/admin.php       —— 历史约定
        $candidates = [
            $base . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'menu' . DIRECTORY_SEPARATOR . 'admin.php',
            $base . DIRECTORY_SEPARATOR . 'menu' . DIRECTORY_SEPARATOR . 'admin.php',
        ];

        $adminFile = null;
        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                $adminFile = $candidate;
                break;
            }
        }

        if ($adminFile === null) {
            return [];
        }

        $tree = include $adminFile;
        if (!is_array($tree)) {
            return [];
        }

        return $this->flattenMenu($tree, $code);
    }

    /**
     * 嵌套菜单 → 展平(带 code 自动生成)
     */
    protected function flattenMenu(array $items, string $pluginKey, int $level = 1, int &$index = 0, ?string $parentCode = null): array
    {
        $out = [];
        foreach ($items as $item) {
            $index++;
            $code = $item['code'] ?? $this->autoCode($item, $pluginKey, $level, $index);
            $out[] = [
                'code'      => $code,
                'title'     => (string)($item['name'] ?? $item['title'] ?? ''),
                'path'      => (string)($item['path'] ?? ''),
                'component' => (string)($item['component'] ?? ''),
                'icon'      => (string)($item['icon'] ?? ''),
                'sort'      => (int)($item['sort'] ?? 0),
                'type'      => (int)($item['type'] ?? ($level === 1 ? 1 : 2)),
                'is_show'   => (int)($item['is_show'] ?? 1),
                'is_sync'   => (int)($item['is_sync'] ?? 0),
                'pid'       => 0,
                'pid_code'  => $parentCode ?? ($item['pid_code'] ?? null),
                'level'     => $level,
                'app'       => $item['app'] ?? 'admin',
                '_plugin_key' => $pluginKey,
            ];

            if (!empty($item['children']) && is_array($item['children'])) {
                $out = array_merge($out, $this->flattenMenu($item['children'], $pluginKey, $level + 1, $index, $code));
            }
        }
        return $out;
    }

    /**
     * 与 MenuTrait::normalizeMenuItem 保持一致的 code 自动生成
     */
    protected function autoCode(array $item, string $plugin, int $level, int $index): string
    {
        $path = (string)($item['path'] ?? '');
        if ($path !== '') {
            $code = trim($path, '/');
            return str_replace('/', ':', $code);
        }
        return $plugin . ':level' . $level . '_' . $index;
    }

    /**
     * 写/更新 TenantPlugin 记录
     */
    protected function upsertTenantRecord(string $tenantId, string $code, string $version, string $syncStatus, ?string $errorMsg = null): void
    {
        /** @var TenantPluginDao $dao */
        $dao = Container::make(TenantPluginDao::class);
        $existing = $dao->findByTenantAndKey($tenantId, $code);

        $now = time();
        $isolationMode = $this->resolveIsolationMode((string)$tenantId);
        $tenantId = (string)$tenantId;

        if (!$existing) {
            // 授权治理记录(授权治理表) + 运行态冗余镜像(过渡期保留列, 最终清理删除)
            $auth = TenantPlugin::create([
                'id'             => (string)Snowflake::generate(),
                'tenant_id'      => $tenantId,
                'plugin_key'     => $code,
                'auth_status'    => 'active',
                'is_purchased'   => 1,
                'status'         => 1,
                'installed_at'   => $now,
                'sync_status'    => $syncStatus,
                'isolation_mode' => $isolationMode,
                'version'        => $version,
                'created_at'     => $now,
                'updated_at'     => $now,
            ]);
            // 运行态记录(运行态表, 同 id 关联)
            \app\model\plugin\TenantPluginInstall::updateOrCreate(
                ['tenant_id' => $tenantId, 'plugin_key' => $code],
                [
                    'id'             => $auth->id,
                    'tenant_id'      => $tenantId,
                    'plugin_key'     => $code,
                    'status'         => 1,
                    'installed_at'   => $now,
                    'sync_status'    => $syncStatus,
                    'isolation_mode' => $isolationMode,
                    'version'        => $version,
                    'created_at'     => $now,
                    'updated_at'     => $now,
                ]
            );
            return;
        }

        // 计算运行态变更: 同时镜像写入授权表冗余列(过渡期)与运行态表
        $update = [
            'sync_status' => $syncStatus,
            'updated_at'  => $now,
        ];
        // running → 不动 version; success → 更新; failed → 保留旧 version 留痕
        if ($syncStatus === 'success') {
            $update['version'] = $version;
            $update['isolation_mode'] = $isolationMode;
            $update['deprecated_version'] = null;
        }
        if ($syncStatus === 'failed' && $errorMsg) {
            $update['deprecated_version'] = $existing->version; // 来自 install 表的当前版本
        }

        $existing->fill(array_merge([
            'auth_status'  => $existing->auth_status ?: 'active',
            'is_purchased' => $existing->is_purchased ?: 1,
        ], $update));
        $existing->save();

        \app\model\plugin\TenantPluginInstall::updateOrCreate(
            ['tenant_id' => $tenantId, 'plugin_key' => $code],
            $update + ['tenant_id' => $tenantId, 'plugin_key' => $code]
        );
    }

    /**
     * 占用清单阻断(平台卸载时,若无 force=true 禁止继续)
     */
    protected function guardNoOccupants(string $code): void
    {
        /** @var TenantPluginDao $dao */
        $dao = Container::make(TenantPluginDao::class);
        $count = $dao->countInstalledTenants($code);
        if ($count > 0) {
            $sample = array_slice($dao->getInstalledTenants($code), 0, 10);
            throw new \RuntimeException(sprintf(
                '插件 [%s] 仍有 %d 个租户占用,无法卸载. 占用样例: %s. 如需强制卸载请使用 cascadeUninstall(force=true).',
                $code,
                $count,
                json_encode(array_column($sample, 'tenant_id'), JSON_UNESCAPED_UNICODE)
            ));
        }
    }

    /**
     * 单体模式断言: ForTenant 在单体下应走 Platform 入口
     */
    protected function assertMultiMode(string $method): void
    {
        if (TenantContext::isSingleMode()) {
            throw new \BadMethodCallException("{$method} 仅在多租户模式下可用, 单体模式请使用 *Platform() 入口");
        }
    }

    /**
     * 临时切换租户上下文(供 installForTenant/updateForTenant/uninstallForTenant)
     *
     * 注: TenantContext 静态状态切换需谨慎, 这里只在调用栈内有效.
     * 若 TenantContext 不支持 setTenantId, 则降级为不切换(由 installClass 自取).
     */
    protected function markTenantContext(string $tenantId): void
    {
        try {
            TenantContext::setTenantId($tenantId);
        } catch (\Throwable $e) {
            // 静默降级: 不支持 setTenantId 时, 后续 installClass 仍可通过自身上下文感知
            $this->logInfo('TenantContext::setTenantId unavailable, skipping', compact('tenantId'));
        }
    }

    /**
     * 按【租户维度】解析隔离模式
     *
     * 批量/队列场景中不同租户的隔离模式可能不同(field/database 混杂),
     * 必须读该租户自身的 Tenant.database_mode, 而不能依赖全局 TenantContext:
     * 全局上下文在批量循环里是上一个租户的残留值, 会导致菜单/迁移写到错误的库。
     */
    protected function resolveIsolationMode(?string $tenantId = null): string
    {
        if (TenantContext::isSingleMode()) {
            return 'single';
        }

        if ($tenantId !== null && $tenantId !== '') {
            $mode = Tenant::withoutGlobalScopes()
                ->where('id', (string)$tenantId)
                ->value('database_mode');
            if (in_array($mode, ['field', 'database'], true)) {
                return $mode;
            }
        }

        return TenantContext::getIsolationMode() ?: 'field';
    }

    protected function mode(): string
    {
        return TenantContext::isSingleMode() ? 'single' : 'multi';
    }

    /**
     * 上报单步骤进度(仅用于 install/update/uninstall ForTenant 的 SSE 反馈)
     */
    protected function notifyStep(?callable $cb, string $msg): void
    {
        if ($cb) {
            $cb($msg);
        }
    }

    protected function logInfo(string $msg, array $ctx = []): void
    {
        try {
            Log::channel('plugin')->info("[Orchestrator] {$msg}", $ctx);
        } catch (\Throwable $e) {
        }
    }

    protected function logError(string $msg, \Throwable $e, array $ctx = []): void
    {
        try {
            Log::channel('plugin')->error("[Orchestrator] {$msg}: " . $e->getMessage(), $ctx + [
                'exception' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
        } catch (\Throwable $ignore) {
        }
    }
}
