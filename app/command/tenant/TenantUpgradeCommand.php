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

use app\command\BaseCommand;
use app\model\tenant\Tenant;
use app\service\core\tenant\TenantMigrationService;
use support\Container;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * 租户升级命令
 *
 * 遍历活跃租户，自动对比并推送待执行的迁移
 */
class TenantUpgradeCommand extends BaseCommand
{
    protected static string $defaultName = 'madong-tenant:upgrade';
    protected static string $defaultDescription = '升级租户数据库（推送待执行的迁移到租户库）';

    protected function configure(): void
    {
        $this->addOption('tenant-id', null, InputOption::VALUE_OPTIONAL, '指定租户ID');
        $this->addOption('all', null, InputOption::VALUE_NONE, '全部活跃租户');
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, '预览模式（不实际执行）');
        $this->addOption('type', null, InputOption::VALUE_OPTIONAL, '类型: migration|plugin|all', 'migration');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $tenantId = $input->getOption('tenant-id');
        $all = $input->getOption('all');
        $dryRun = $input->getOption('dry-run');
        $type = $input->getOption('type');

        if (!$tenantId && !$all) {
            $output->writeln('<error>❌ 请指定 --tenant-id 或使用 --all</error>');
            return Command::INVALID;
        }

        if ($dryRun) {
            $output->writeln('<comment>🔍 DRY RUN MODE - No changes will be made</comment>');
        }

        /** @var TenantMigrationService $service */
        $service = Container::make(TenantMigrationService::class);

        // 获取目标租户列表
        $tenants = $this->getTargetTenants($tenantId);

        if (empty($tenants)) {
            $output->writeln('<comment>ℹ️ 没有找到目标租户</comment>');
            return Command::SUCCESS;
        }

        $output->writeln("<info>📊 Found " . count($tenants) . " tenant(s) to upgrade</info>");
        $output->writeln(str_repeat('=', 50));

        $totalSuccess = 0;
        $totalFailed = 0;

        foreach ($tenants as $tenant) {
            $output->writeln("");
            $output->writeln("<info>🔧 Processing tenant: {$tenant->id} ({$tenant->name})</info>");

            if ($dryRun) {
                $output->writeln("  (dry-run, skipped)");
                continue;
            }

            if ($type === 'migration' || $type === 'all') {
                $result = $service->executeMainMigrations($tenant->id);
                $output->writeln("  📋 Migrations: executed={$result['executed']}, pending={$result['pending']}");

                if (!empty($result['errors'])) {
                    foreach ($result['errors'] as $error) {
                        $output->writeln("  <error>❌ {$error['file']}: {$error['error']}</error>");
                    }
                    $totalFailed++;
                } else {
                    $totalSuccess++;
                }
            }

            // 插件同步（后续可通过 --type=plugin 单独执行）
            if ($type === 'plugin' || $type === 'all') {
                $output->writeln("  <comment>ℹ️ Plugin sync: use event-driven mechanism (auto-triggered on plugin ops)</comment>");
            }
        }

        $output->writeln("");
        $output->writeln(str_repeat('=', 50));
        $output->writeln("<info>✅ Complete: success={$totalSuccess}, failed={$totalFailed}</info>");

        return Command::SUCCESS;
    }

    /**
     * 获取目标租户列表
     */
    protected function getTargetTenants(?string $tenantId): array
    {
        if ($tenantId) {
            $tenant = Tenant::withoutGlobalScopes()->find($tenantId);
            return $tenant ? [$tenant] : [];
        }

        return Tenant::withoutGlobalScopes()
            ->where('status', Tenant::STATUS_ACTIVE)
            ->where('database_mode', Tenant::MODE_DATABASE)
            ->get()
            ->all();
    }
}
