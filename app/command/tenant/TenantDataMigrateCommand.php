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

namespace app\command\tenant;

use app\model\tenant\Tenant;
use core\business\tenant\TenantConnectionManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * 租户数据迁移命令
 *
 * 将 field 模式（字段隔离）的租户数据迁移到 database 模式（库隔离）
 * 主库和租户库的 schema 完全一致（都包含 tenant_id），
 * 因此统一使用 INSERT ... SELECT WHERE tenant_id = ? 复制数据。
 *
 * 使用方法：
 *   php webman tenant:migrate-data --tenant-id=1,2,3
 *   php webman tenant:migrate-data --all
 *   php webman tenant:migrate-data --all --dry-run
 *   php webman tenant:migrate-data --all --force
 *
 * @author Mr.April
 * @since 1.0.0
 */
#[AsCommand(
    name: 'tenant:migrate-data',
    description: 'Migrate tenant data from field isolation to database isolation',
    hidden: false
)]
class TenantDataMigrateCommand extends Command
{
    /**
     * 需迁移的表名列表
     *
     * 主库和租户库的 schema 一致，全表都包含 tenant_id 列。
     * 数据复制统一使用 INSERT ... SELECT WHERE tenant_id = ?。
     *
     * @var array
     */
    private array $tableNames = [
        'sys_admin',
        'sys_admin_main',
        'sys_admin_role',
        'sys_admin_dept',
        'sys_admin_post',
        'sys_role',
        'sys_menu',
        'sys_role_menu',
        'sys_config',
        'sys_dept',
        'sys_post',
        'sys_role_dept',
        'sys_role_scope_dept',
    ];

    /**
     * @var SymfonyStyle
     */
    private SymfonyStyle $io;

    /**
     * 配置命令
     */
    protected function configure(): void
    {
        $this->addOption('tenant-id', 't', InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
            '要迁移的租户ID（支持逗号分隔或多次指定）');
        $this->addOption('all', 'a', InputOption::VALUE_NONE,
            '迁移所有 field 模式且状态为 active 的租户');
        $this->addOption('dry-run', null, InputOption::VALUE_NONE,
            '预览模式：只显示将执行的操作，不实际执行');
        $this->addOption('force', 'f', InputOption::VALUE_NONE,
            '跳过确认提示');
    }

    /**
     * 执行命令
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new SymfonyStyle($input, $output);
        $this->io->title('Tenant Data Migration — Field → Database Isolation');

        // ---- 1. 解析目标租户 ----
        $tenants = $this->resolveTenants($input);
        if (empty($tenants)) {
            $this->io->warning('No tenants found to migrate.');
            $this->io->text('Use --tenant-id=... or --all to specify target tenants.');
            return Command::SUCCESS;
        }

        // ---- 2. 检查依赖 ----
        if (!$this->checkTenantMigrationFiles()) {
            return Command::FAILURE;
        }

        // ---- 3. 确认 ----
        $isDryRun = (bool)$input->getOption('dry-run');
        $force = (bool)$input->getOption('force');

        $this->printMigrationPlan($tenants, $isDryRun);

        if (!$force && !$isDryRun) {
            if (!$this->io->confirm('Continue with migration?', false)) {
                $this->io->warning('Migration cancelled.');
                return Command::SUCCESS;
            }
        }

        // ---- 4. 执行迁移 ----
        $successCount = 0;
        $failCount = 0;

        foreach ($tenants as $tenant) {
            if ($isDryRun) {
                $this->io->note("[DRY-RUN] Would migrate tenant #{$tenant['id']} ({$tenant['name']})");
                $successCount++;
                continue;
            }

            $result = $this->migrateTenant($tenant);
            if ($result) {
                $successCount++;
            } else {
                $failCount++;
            }
        }

        // ---- 5. 结果汇总 ----
        $this->io->newLine();
        if ($isDryRun) {
            $this->io->success("[DRY-RUN] Preview complete. {$successCount} tenant(s) would be migrated.");
        } elseif ($failCount === 0) {
            $this->io->success("Migration complete. {$successCount} tenant(s) migrated successfully.");
        } else {
            $this->io->warning("Migration finished with errors: {$successCount} succeeded, {$failCount} failed.");
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    /**
     * 解析要迁移的租户列表
     */
    private function resolveTenants(InputInterface $input): array
    {
        $tenantIds = $input->getOption('tenant-id');
        $all = (bool)$input->getOption('all');

        // 收集所有 tenant IDs
        $ids = [];
        foreach ($tenantIds as $value) {
            // 可能传入逗号分隔的字符串
            $parts = explode(',', $value);
            foreach ($parts as $part) {
                $part = trim($part);
                if ($part !== '') {
                    $ids[] = $part;
                }
            }
        }

        $query = Tenant::withoutGlobalScopes()->where('status', Tenant::STATUS_ACTIVE);

        if (!empty($ids)) {
            // 指定租户：不限制模式，但状态必须 active（兼容已部分迁移的情况）
            $query->whereIn('id', $ids);
        } elseif ($all) {
            // 全部：只选 field 模式的活跃租户
            $query->where('database_mode', Tenant::MODE_FIELD);
        } else {
            return [];
        }

        $records = $query->get();
        $tenants = [];
        foreach ($records as $record) {
            $tenants[] = [
                'id' => $record->id,
                'name' => $record->name,
                'code' => $record->code,
                'current_mode' => $record->database_mode,
                'has_database' => !empty($record->database_name),
            ];
        }

        return $tenants;
    }

    /**
     * 检查租户迁移文件是否存在
     */
    private function checkTenantMigrationFiles(): bool
    {
        $path = base_path() . '/' . config('tenant.database_isolation.migration_path', 'database/tenant_migrations');
        if (!is_dir($path)) {
            $this->io->error("Tenant migrations directory not found: {$path}");
            $this->io->text('Please create the migration files first.');
            return false;
        }

        $files = glob($path . '/*.php');
        if (empty($files)) {
            $this->io->error("No migration files found in: {$path}");
            return false;
        }

        return true;
    }

    /**
     * 打印迁移计划
     */
    private function printMigrationPlan(array $tenants, bool $isDryRun): void
    {
        $modeLabel = $isDryRun ? '[DRY-RUN] ' : '';
        $this->io->section("{$modeLabel}Migration Plan");

        $this->io->text(sprintf('Tenants to migrate: <info>%d</info>', count($tenants)));
        $this->io->newLine();

        $rows = [];
        foreach ($tenants as $t) {
            $mode = $t['current_mode'] === Tenant::MODE_DATABASE ? 'database' : 'field';
            $rows[] = [
                $t['id'],
                $t['name'],
                $t['code'],
                $mode,
                $t['has_database'] ? '<info>exists</info>' : '<comment>not created</comment>',
            ];
        }

        $this->io->table(
            ['ID', 'Name', 'Code', 'Current Mode', 'Target DB'],
            $rows
        );

        $this->io->newLine();
        $this->io->text('Tables to migrate:');
        foreach ($this->tableNames as $table) {
            $this->io->text(sprintf('  • <comment>%s</comment> — WHERE tenant_id = ?', $table));
        }
        $this->io->newLine();
    }

    /**
     * 迁移单个租户
     *
     * @param array $tenant
     * @return bool
     */
    private function migrateTenant(array $tenant): bool
    {
        $tenantId = $tenant['id'];
        $tenantName = $tenant['name'];

        // 防重复迁移：已经是 database 模式且有数据库的租户直接跳过
        if ($tenant['current_mode'] === Tenant::MODE_DATABASE && $tenant['has_database']) {
            $this->io->note("Tenant #{$tenantId} is already in database mode, skipping.");
            return true;
        }

        $this->io->section("Migrating Tenant #{$tenantId}: {$tenantName}");

        try {
            // ---- Step 1: 创建租户数据库 ----
            $this->io->text('Step 1/4: Ensuring tenant database exists...');
            $this->ensureTenantDatabase($tenantId);

            // ---- Step 2: 运行租户迁移 ----
            $this->io->text('Step 2/4: Running tenant migrations...');
            $this->runTenantMigrations($tenantId);

            // ---- Step 3: 复制数据 ----
            $this->io->text('Step 3/4: Copying data from main DB to tenant DB...');
            $copyResult = $this->copyTenantData($tenantId);

            // 只有全部成功才更新模式
            if ($copyResult['success'] && $copyResult['failed_count'] === 0) {
                // ---- Step 4: 更新租户模式 ----
                $this->io->text('Step 4/4: Updating tenant mode to database...');
                $this->updateTenantMode($tenantId);
                $this->io->success("Tenant #{$tenantId} migrated successfully. ({$copyResult['total_rows']} rows copied)");
                Log::info("Tenant data migrated: id={$tenantId}, name={$tenantName}, mode=field->database, rows={$copyResult['total_rows']}");
            } else {
                $this->io->warning("Tenant #{$tenantId}: {$copyResult['succeeded']}/{$copyResult['total_tables']} tables copied ({$copyResult['failed_count']} failed). Mode NOT updated.");
                Log::warning("Tenant data partially migrated: id={$tenantId}, name={$tenantName}, "
                    . "succeeded={$copyResult['succeeded']}/{$copyResult['total_tables']}, rows={$copyResult['total_rows']}");
            }

            return true;

        } catch (\Throwable $e) {
            $this->io->error("Migration failed for tenant #{$tenantId}: {$e->getMessage()}");
            Log::error("Tenant migration failed: id={$tenantId}, error={$e->getMessage()}", [
                'trace' => $e->getTraceAsString(),
            ]);
            return false;
        }
    }

    /**
     * 确保租户数据库存在
     */
    private function ensureTenantDatabase(int|string $tenantId): void
    {
        TenantConnectionManager::ensureDatabaseExists($tenantId);
    }

    /**
     * 对租户库运行迁移
     */
    private function runTenantMigrations(int|string $tenantId): void
    {
        $migrationPath = config('tenant.database_isolation.migration_path', 'database/tenant_migrations');
        $path = base_path() . '/' . $migrationPath;
        $files = glob($path . '/*.php');

        if (empty($files)) {
            $this->io->warning('No migration files found, skipping.');
            return;
        }

        $migrationCount = 0;
        foreach ($files as $file) {
            $migrationName = basename($file, '.php');
            $this->io->text("  Running migration: {$migrationName}...");

            TenantConnectionManager::setCurrentConnection($tenantId, false);

            try {
                $instance = require $file;
                if (is_object($instance) && method_exists($instance, 'up')) {
                    $instance->up();
                    $migrationCount++;
                    $this->io->text("  ✓ {$migrationName}");
                }
            } catch (\Throwable $e) {
                $this->io->warning("  ⚠ Migration {$migrationName}: {$e->getMessage()}");
            } finally {
                TenantConnectionManager::releaseCurrentConnection();
            }
        }

        $this->io->text("  Migrations executed: {$migrationCount} file(s)");
    }

    /**
     * 从主库复制租户数据到租户独立库
     *
     * 主库和租户库的 schema 完全一致（都包含 tenant_id），
     * 因此使用统一的 INSERT ... SELECT WHERE tenant_id = ? 即可。
     *
     * @param int|string $tenantId
     * @return array ['success' => bool, 'failed_count' => int, 'total_rows' => int, 'succeeded' => int, 'total_tables' => int]
     */
    private function copyTenantData(int|string $tenantId): array
    {
        $targetDbName = TenantConnectionManager::getTenantDatabaseName($tenantId);
        $sourceDbName = config('database.connections.mysql.database', '');

        $result = [
            'success'       => false,
            'failed_count'  => 0,
            'succeeded'     => 0,
            'total_rows'    => 0,
            'total_tables'  => count($this->tableNames),
        ];

        if (empty($sourceDbName)) {
            $this->io->error('Cannot determine source database name from config.');
            return $result;
        }

        // 确保租户库连接已注册
        TenantConnectionManager::setCurrentConnection($tenantId, false);

        // 主库连接（用于跨库 INSERT ... SELECT）
        $connection = DB::connection();

        foreach ($this->tableNames as $table) {
            $this->io->text("  Copying <comment>{$table}</comment>...");

            try {
                // 动态获取 source 表的所有列（包括 tenant_id）
                $cols = $this->getTableColumns($table, $sourceDbName);
                if (empty($cols)) {
                    $this->io->warning("  ⚠ Cannot get columns for `{$table}`, skipping.");
                    $result['failed_count']++;
                    continue;
                }
                $colsList = implode(', ', array_map(fn($c) => "`{$c}`", $cols));

                $sql = "INSERT INTO `{$targetDbName}`.`{$table}` ({$colsList})
                        SELECT {$colsList} FROM `{$sourceDbName}`.`{$table}`
                        WHERE `tenant_id` = ? AND `deleted_at` IS NULL";

                $rows = $this->executeAffectingStatement($connection, $sql, [$tenantId]);

                $result['total_rows'] += $rows;
                $result['succeeded']++;
                $this->io->text("    → {$rows} row(s) copied");

            } catch (\Throwable $e) {
                $this->io->warning("  ⚠ `{$table}` copy error: {$e->getMessage()}");
                $result['failed_count']++;
                // 不中断整体流程，继续下一个表
            }
        }

        $this->io->text("  Total rows copied: {$result['total_rows']}");
        $result['success'] = true;
        return $result;
    }

    /**
     * 执行影响行数的 SQL 语句
     *
     * 兼容不同版本 Laravel ORM 的 affectingStatement 方法
     *
     * @param \Illuminate\Database\Connection $connection
     * @param string  $sql
     * @param array   $bindings
     * @return int
     */
    private function executeAffectingStatement(\Illuminate\Database\Connection $connection, string $sql, array $bindings = []): int
    {
        // Laravel 8+ 原生方法
        if (method_exists($connection, 'affectingStatement')) {
            return $connection->affectingStatement($sql, $bindings);
        }

        // 回退：使用 PDO 直接执行
        $statement = $connection->getPdo()->prepare($sql);
        $statement->execute($bindings);
        return $statement->rowCount();
    }

    /**
     * 更新租户模式为 database
     */
    private function updateTenantMode(int|string $tenantId): void
    {
        $databaseName = TenantConnectionManager::getTenantDatabaseName($tenantId);

        Tenant::withoutGlobalScopes()
            ->where('id', $tenantId)
            ->update([
                'database_mode' => Tenant::MODE_DATABASE,
                'database_name' => $databaseName,
            ]);

        $this->io->text("  Tenant #{$tenantId} updated: database_mode=database, database_name={$databaseName}");
    }

    /**
     * 获取指定数据库的表的列名列表
     *
     * @param string $table
     * @param string $database
     * @return array
     */
    private function getTableColumns(string $table, string $database): array
    {
        try {
            $result = DB::select("SHOW COLUMNS FROM `{$database}`.`{$table}`");
            $columns = [];
            foreach ($result as $row) {
                $columns[] = $row->Field;
            }
            return $columns;
        } catch (\Throwable $e) {
            $this->io->warning("  ⚠ Cannot describe `{$database}`.`{$table}`: {$e->getMessage()}");
            return [];
        }
    }
}
