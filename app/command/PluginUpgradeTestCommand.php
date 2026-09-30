<?php

declare(strict_types=1);

/**
 * 插件升级隔离模式验证
 *
 * 验证「调整插件版本 + 改动迁移」后, 升级流程在两种隔离模式下都能把结构变更落到正确的库:
 *   - field(字段隔离)   : 共享主库, 租户侧迁移应【跳过】, 结构由平台层 madong-plugin-migrate up 统一维护
 *   - database(库隔离)  : 迁移应在租户独立库 tenant_{id} 上执行, 新列落在租户库
 *
 * 用法:
 *   php webman madong-test:plugin-upgrade            # 跑两种隔离模式验证 (结束自动清理)
 *   php webman madong-test:plugin-upgrade --keep     # 保留测试用的租户库, 不清理
 */

namespace app\command;

use app\command\BaseCommand;
use app\model\tenant\Tenant;
use app\service\core\plugin\TenantPluginMigrationService;
use core\business\tenant\SyncConnection;
use core\business\tenant\TenantConnectionManager;
use support\Container;
use support\Db;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'madong-test:plugin-upgrade',
    description: '验证插件升级在 field / database 两种隔离模式下是否生效'
)]
class PluginUpgradeTestCommand extends BaseCommand
{
    protected function configure(): void
    {
        $this->addOption('keep', null, InputOption::VALUE_NONE, '保留测试用的租户库, 不清理');
    }

    public function __invoke(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('插件升级 · 隔离模式验证 (demo 1.1.0)');

        $plugin  = 'demo';
        $version = '1.1.0';
        // 与迁移文件保持一致的前缀解析方式
        $prefix  = config('admin.database.table_prefix') ?? '';
        $table   = $prefix . 'demo_demo_test';

        /** @var TenantPluginMigrationService $mig */
        $mig = Container::make(TenantPluginMigrationService::class);

        // ───────── 字段隔离 (field) ─────────
        $io->section('① 字段隔离 field：租户侧迁移应跳过, 结构在主库');
        $fieldTid = '90000001';
        $rField = $mig->run($plugin, $fieldTid, 'update', $version, 'field');
        $io->writeln('   run() 返回: ' . json_encode($rField, JSON_UNESCAPED_UNICODE));

        $mainHasCol = Db::connection(TenantConnectionManager::getDefaultConnectionName())->getSchemaBuilder()->hasColumn($table, 'extra_col');
        $io->writeln("   主库 {$table}.extra_col 存在: " . ($mainHasCol ? '是' : '否'));

        $fieldPass = ($rField['skipped'] ?? false) === true && $mainHasCol;
        $io->writeln($fieldPass ? '  <info>✅ PASS</info>' : '  <error>❌ FAIL</error>');

        // ───────── 库隔离 (database) ─────────
        $io->section('② 库隔离 database：迁移应在租户独立库执行');
        $dbTid = '90000002';
        $dbName = 'saas_tenant_test_upgrade';

        // 1) 建独立库
        Db::connection(TenantConnectionManager::getDefaultConnectionName())->statement("CREATE DATABASE IF NOT EXISTS `{$dbName}`");
        // 2) 建租户记录(database_mode=database, 指向该库)
        $tenant = new Tenant();
        $tenant->id = $dbTid;
        $tenant->name = 'upgrade-test';
        $tenant->code = 'upgrade-test';
        $tenant->status = Tenant::STATUS_ACTIVE;
        $tenant->database_mode = Tenant::MODE_DATABASE;
        $tenant->database_name = $dbName;
        $tenant->save();
        $io->writeln("   已建租户记录 id={$dbTid} database_mode=database database_name={$dbName}");

        // 3) 在租户独立库跑迁移
        $rDb = $mig->run($plugin, $dbTid, 'update', $version, 'database');
        $io->writeln('   run() 返回: ' . json_encode($rDb, JSON_UNESCAPED_UNICODE));

        $tenantConn = 'tenant_' . $dbTid;
        $dbHasCol = Db::connection($tenantConn)->getSchemaBuilder()->hasColumn($table, 'extra_col');
        $io->writeln("   租户库 {$tenantConn}.{$table}.extra_col 存在: " . ($dbHasCol ? '是' : '否'));

        $dbPass = ($rDb['skipped'] ?? true) === false && $dbHasCol;
        $io->writeln($dbPass ? '  <info>✅ PASS</info>' : '  <error>❌ FAIL</error>');

        // ───────── 清理 ─────────
        if (!$input->getOption('keep')) {
            $io->section('清理测试数据');
            try {
                Db::connection(TenantConnectionManager::getDefaultConnectionName())->statement("DROP DATABASE IF EXISTS `{$dbName}`");
                Tenant::withoutGlobalScopes()->where('id', $dbTid)->forceDelete();
                $io->writeln("   已删除租户库 {$dbName} 与租户记录 {$dbTid}");
            } catch (\Throwable $e) {
                $io->writeln('   <comment>清理异常(可忽略): ' . $e->getMessage() . '</comment>');
            }
        } else {
            $io->note("保留测试租户库 {$dbName} 与租户记录 {$dbTid}（--keep）");
        }

        $io->success(sprintf('字段隔离: %s | 库隔离: %s', $fieldPass ? 'PASS' : 'FAIL', $dbPass ? 'PASS' : 'FAIL'));

        return ($fieldPass && $dbPass) ? self::SUCCESS : self::FAILURE;
    }
}
