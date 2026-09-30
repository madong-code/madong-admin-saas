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
namespace app\service\core\tenant;

use app\dao\tenant\TenantMigrationDao;
use app\model\tenant\Tenant;
use core\business\tenant\TenantConnectionManager;
use core\foundation\base\BaseService;
use core\io\uuid\Snowflake;
use support\Db;

/**
 * 租户迁移执行服务
 *
 * 管理租户维度迁移状态的记录和查询
 */
class TenantMigrationService extends BaseService
{
    /** @var string 主项目迁移文件目录 */
    protected string $mainMigrationPath;

    public function __construct(TenantMigrationDao $dao)
    {
        $this->dao = $dao;
        $this->mainMigrationPath = base_path('resource/database/migrations');
    }

    /**
     * 获取租户已执行的迁移列表
     */
    public function getExecutedMigrations(int|string $tenantId, string $type = 'migrate'): array
    {
        $records = $this->dao->getList([
            'tenant_id' => $tenantId,
            'type' => $type,
            'status' => 'success',
        ], 'target', 0, 0, 'id asc');

        return array_column($records, 'target');
    }

    /**
     * 记录迁移执行
     */
    public function recordMigration(
        int|string $tenantId,
        string $type,
        string $target,
        int $batch = 1,
        string $status = 'success',
        ?string $versionBefore = null,
        ?string $versionAfter = null,
        ?string $errorMessage = null,
        ?int $startedAt = null,
        ?int $finishedAt = null
    ): void {
        $now = time();
        $this->dao->save([
            'id' => Snowflake::generate(),
            'tenant_id' => $tenantId,
            'type' => $type,
            'target' => $target,
            'batch' => $batch,
            'status' => $status,
            'version_before' => $versionBefore,
            'version_after' => $versionAfter,
            'error_message' => $errorMessage,
            'started_at' => $startedAt ?? $now,
            'finished_at' => $finishedAt,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /**
     * 获取租户待执行的迁移对比（通过比较日志文件和表记录）
     *
     * @param int|string $tenantId
     * @param array $allMigrationFiles 当前主项目的所有迁移文件列表
     * @return array 待执行的迁移文件名列表
     */
    public function getPendingMigrations(int|string $tenantId, array $allMigrationFiles): array
    {
        $executed = $this->getExecutedMigrations($tenantId, 'migrate');
        return array_values(array_diff($allMigrationFiles, $executed));
    }

    /**
     * 同步插件操作到所有已启用且为 database 模式的租户
     *
     * WP6 修复: 原实现仅按 database_mode 过滤, 会执行未授权/未真正安装的租户
     * 现按 is_purchased=1 (真实购买/授权) + status=1 (启用) 过滤真实安装者
     *
     * @param string $pluginName
     * @param string $version
     * @param string $action install|uninstall|update
     */
    public function syncPluginToTenants(string $pluginName, string $version, string $action = 'install'): void
    {
        $tenants = Tenant::withoutGlobalScopes()
            ->where('status', Tenant::STATUS_ACTIVE)
            ->where('database_mode', Tenant::MODE_DATABASE)
            ->get();

        foreach ($tenants as $tenant) {
            $tenantId = $tenant->id;
            $startedAt = time();

            // WP6: 真实安装者过滤 — 必须是已购买/已授权 + 启用 + 记录存在
            $realInstaller = \app\dao\plugin\TenantPluginDao::class;
            /** @var \app\dao\plugin\TenantPluginDao $pluginDao */
            $pluginDao = \support\Container::make($realInstaller);
            $record = $pluginDao->findByTenantAndKey($tenantId, $pluginName);
            if (!$record || (int)$record->status !== 1 || (int)$record->is_purchased !== 1) {
                // 跳过: 未授权 / 未安装 / 已停用 的租户
                continue;
            }

            try {
                $this->recordMigration(
                    $tenantId,
                    $action,
                    $pluginName,
                    0,
                    'running',
                    null,
                    $version,
                    null,
                    $startedAt
                );

                // 在租户库上执行插件迁移
                TenantConnectionManager::setCurrentConnection($tenantId, false);

                try {
                    // 获取插件安装器并在租户上下文执行
                    $installClass = $this->getPluginInstallClass($pluginName);
                    if ($installClass) {
                        $installer = new $installClass();
                        $installer->setContext('tenant');
                        $installer->setTenantId($tenantId);

                        match ($action) {
                            'install' => $installer->install($version),
                            'uninstall' => $installer->uninstall($version),
                            'update' => $installer->update($version),
                        };
                    }

                    $this->recordMigration(
                        $tenantId,
                        $action,
                        $pluginName,
                        0,
                        'success',
                        null,
                        $version,
                        null,
                        $startedAt,
                        time()
                    );
                } finally {
                    TenantConnectionManager::releaseCurrentConnection();
                }
            } catch (\Throwable $e) {
                $this->recordMigration(
                    $tenantId,
                    $action,
                    $pluginName,
                    0,
                    'failed',
                    null,
                    $version,
                    $e->getMessage(),
                    $startedAt,
                    time()
                );
            }
        }
    }

    /**
     * 执行主项目迁移到指定租户
     */
    public function executeMainMigrations(int|string $tenantId): array
    {
        $files = glob($this->mainMigrationPath . '/*.php');
        $allMigrations = array_map(fn($f) => basename($f, '.php'), $files);
        sort($allMigrations);

        $pending = $this->getPendingMigrations($tenantId, $allMigrations);

        if (empty($pending)) {
            return ['executed' => 0, 'pending' => 0, 'errors' => []];
        }

        $errors = [];
        $executed = 0;
        $startedAt = time();
        $batch = $this->getNextBatch($tenantId);

        TenantConnectionManager::setCurrentConnection($tenantId, false);

        try {
            $schema = Db::connection()->getSchemaBuilder();

            foreach ($pending as $migration) {
                $file = $this->mainMigrationPath . '/' . $migration . '.php';
                if (!file_exists($file)) {
                    continue;
                }

                try {
                    $instance = require $file;
                    if (is_object($instance) && method_exists($instance, 'up')) {
                        $instance->up($schema);
                    }

                    $this->recordMigration(
                        $tenantId,
                        'migrate',
                        $migration,
                        $batch,
                        'success',
                        null, null, null,
                        $startedAt, time()
                    );
                    $executed++;
                } catch (\Throwable $e) {
                    $errors[] = ['file' => $migration, 'error' => $e->getMessage()];

                    $this->recordMigration(
                        $tenantId,
                        'migrate',
                        $migration,
                        $batch,
                        'failed',
                        null, null,
                        $e->getMessage(),
                        $startedAt, time()
                    );
                }
            }
        } finally {
            TenantConnectionManager::releaseCurrentConnection();
        }

        return [
            'executed' => $executed,
            'pending' => count($pending),
            'errors' => $errors,
        ];
    }

    /**
     * 获取下一批次号
     */
    protected function getNextBatch(int|string $tenantId): int
    {
        $last = $this->dao->getList(
            ['tenant_id' => $tenantId],
            'batch',
            0, 1,
            'batch desc'
        );

        return empty($last) ? 1 : ((int) $last[0]['batch'] + 1);
    }

    /**
     * 获取插件安装类
     */
    protected function getPluginInstallClass(string $pluginName): ?string
    {
        $class = "\\plugin\\{$pluginName}\\Install";
        return class_exists($class) ? $class : null;
    }
}
