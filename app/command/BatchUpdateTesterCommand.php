<?php

declare(strict_types=1);

/**
 * 模拟批量更新租户插件测试
 *
 * 用途：
 *   - 验证新表 md_saas_tenant_plugin / md_saas_tenant_plugin_sync_jobs 在批量更新链路下的读写。
 *   - 不依赖真实租户，使用一批虚拟 tenant_id（90000001...）直接驱动 PluginBatchExecutor。
 *
 * 用法：
 *   php webman madong-test:batch-update                       # 内联批量更新 20 个虚拟租户(demo)
 *   php webman madong-test:batch-update --plugin=test1         # 指定插件
 *   php webman madong-test:batch-update --count=60             # 触发队列路径(写审计表)
 *   php webman madong-test:batch-update --queue                # 强制队列路径
 *   php webman madong-test:batch-update --clean                # 结束后清理测试数据
 */

namespace app\command;

use app\command\BaseCommand;
use app\service\core\plugin\PluginBatchExecutor;
use core\business\tenant\context\TenantContext;
use core\business\tenant\TenantConnectionManager;
use core\io\uuid\Snowflake;
use support\Container;
use support\Db;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'madong-test:batch-update',
    description: '模拟批量更新租户插件（验证 saas_tenant_plugin / saas_tenant_plugin_sync_jobs）'
)]
class BatchUpdateTesterCommand extends BaseCommand
{
    protected function configure(): void
    {
        $this->addOption('plugin', null, InputOption::VALUE_OPTIONAL, 'Plugin key', 'demo')
            ->addOption('count', null, InputOption::VALUE_OPTIONAL, '测试租户数量', 20)
            ->addOption('to-version', null, InputOption::VALUE_OPTIONAL, '目标版本', '2.0.0')
            ->addOption('queue', null, InputOption::VALUE_NONE, '强制走队列路径（验证 saas_tenant_plugin_sync_jobs 审计）')
            ->addOption('governance', null, InputOption::VALUE_NONE, '验证升级治理(平台开关 allow_upgrade / 租户忽略 ignored_version)')
            ->addOption('auth-test', null, InputOption::VALUE_NONE, '验证授权互覆盖修复(setAuth 增量授权, 不互相覆盖)')
            ->addOption('clean', null, InputOption::VALUE_NONE, '测试结束后清理测试数据');
    }

    public function __invoke(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $plugin = (string)$input->getOption('plugin');
        $count = max(1, (int)$input->getOption('count'));
        $toVersion = (string)$input->getOption('to-version');
        $forceQueue = (bool)$input->getOption('queue');
        $clean = (bool)$input->getOption('clean');
        $governance = (bool)$input->getOption('governance');
        $authTest = (bool)$input->getOption('auth-test');

        $io->title('模拟批量更新租户插件测试');

        if ($authTest) {
            $io->section('授权互覆盖验证（修复 setAuth 增量语义）');
            /** @var \app\service\platform\plugin\PluginTenantAuthService $authSvc */
            $authSvc    = \support\Container::make(\app\service\platform\plugin\PluginTenantAuthService::class);
            $testTenant = '90009999';

            Db::connection(TenantConnectionManager::getDefaultConnectionName())->table('saas_tenant_plugin')->where('tenant_id', $testTenant)->delete();

            $authSvc->setAuth($testTenant, ['demo'], 'authorized');
            $after1 = Db::connection(TenantConnectionManager::getDefaultConnectionName())->table('saas_tenant_plugin')->where('tenant_id', $testTenant)
                ->orderBy('plugin_key')->pluck('plugin_key')->all();
            $io->writeln('  步骤1 授权 demo  -> 插件列表: ' . json_encode($after1, JSON_UNESCAPED_UNICODE));

            $authSvc->setAuth($testTenant, ['test1'], 'authorized');
            $after2 = Db::connection(TenantConnectionManager::getDefaultConnectionName())->table('saas_tenant_plugin')->where('tenant_id', $testTenant)
                ->orderBy('plugin_key')->pluck('plugin_key')->all();
            $io->writeln('  步骤2 授权 test1 -> 插件列表: ' . json_encode($after2, JSON_UNESCAPED_UNICODE));

            $expect = ['demo', 'test1'];
            if ($after2 === $expect) {
                $io->writeln('  <info>通过: 两个插件授权均保留, 无互覆盖</info>');
            } else {
                $io->writeln('  <error>失败: 期望 ' . json_encode($expect) . ', 实际 ' . json_encode($after2) . '</error>');
            }

            Db::connection(TenantConnectionManager::getDefaultConnectionName())->table('saas_tenant_plugin')->where('tenant_id', $testTenant)->delete();
            $io->writeln('  已清理授权测试数据');

            return self::SUCCESS;
        }

        if (TenantContext::isSingleMode()) {
            $io->error('当前为单体模式，租户批量更新不适用（Orchestrator::updateForTenant 仅多租户可用）。');
            return self::FAILURE;
        }

        $fromVersion = '1.0.0';
        $tenantIds = [];
        for ($i = 1; $i <= $count; $i++) {
            $tenantIds[] = '9000' . str_pad((string)$i, 5, '0', STR_PAD_LEFT);
        }

        $now = time();

        // 1. 准备旧记录（version=1.0.0, 已安装, authorized）
        $prepared = 0;
        foreach ($tenantIds as $tid) {
            $exists = Db::connection(TenantConnectionManager::getDefaultConnectionName())->table('saas_tenant_plugin')
                ->where('tenant_id', $tid)
                ->where('plugin_key', $plugin)
                ->first();
            if (!$exists) {
                Db::connection(TenantConnectionManager::getDefaultConnectionName())->table('saas_tenant_plugin')->insert([
                    'id'            => (string)Snowflake::generate(),
                    'tenant_id'     => $tid,
                    'plugin_key'    => $plugin,
                    'auth_status'   => 'authorized',
                    'status'        => 1,
                    'is_purchased'  => 1,
                    'installed_at'  => $now,
                    'sync_status'   => 'success',
                    'isolation_mode' => 'field',
                    'version'       => $fromVersion,
                    'created_at'    => $now,
                    'updated_at'    => $now,
                ]);
                $prepared++;
            }
        }
        $io->info("准备旧记录({$fromVersion}): 新增 {$prepared}, 复用 " . ($count - $prepared));

        /** @var PluginBatchExecutor $svc */
        $svc = Container::make(PluginBatchExecutor::class);

        if ($forceQueue || $count > 50) {
            // 2a. 队列路径：写审计 + 入队（不消费）
            $io->section('路径: 队列 (enqueue) — 写 saas_tenant_plugin_sync_jobs 审计 + 入队');
            try {
                $result = $svc->enqueue($plugin, $toVersion, 'update', $tenantIds, ['force' => true]);
                $io->success(sprintf('已创建同步任务 jobId=%s total=%d', $result['jobId'] ?? '?', $result['total'] ?? 0));

                $job = Db::connection(TenantConnectionManager::getDefaultConnectionName())->table('saas_tenant_plugin_sync_jobs')->where('id', $result['jobId'])->first();
                $io->writeln(sprintf(
                    '  jobId=%s plugin_key=%s action=%s status=%s progress_total=%s progress_done=%s',
                    $result['jobId'] ?? '?',
                    $job->plugin_key ?? '',
                    $job->action ?? '',
                    $job->status ?? '',
                    $job->progress_total ?? 0,
                    $job->progress_done ?? 0
                ));
                $io->writeln('  tenant_ids=' . json_encode($job->tenant_ids ?? []));
            } catch (\Throwable $e) {
                $io->writeln('<error>enqueue 异常（可能 Redis 未运行，但审计记录应已写入）: ' . $e->getMessage() . '</error>');
            }
            $io->writeln('队列由 PluginSyncJobConsumer 异步消费，此处仅验证审计记录写入。');
        } else {
            // 2b. 内联路径：逐租户 update 1.0.0 -> 2.0.0
            $io->section("路径: 内联 (inline) — 逐租户 update {$fromVersion} -> {$toVersion}");
            $result = $svc->dispatch(
                $plugin,
                $toVersion,
                'update',
                $tenantIds,
                ['force' => true],
                function (string $msg, int $pct, array $extra) use ($io) {
                    $io->writeln("  [{$pct}%] {$msg}");
                }
            );
            $io->success(sprintf(
                '内联执行完成: processed=%d/%d, errors=%d',
                $result['processed'] ?? 0,
                $result['total'] ?? 0,
                count($result['errors'] ?? [])
            ));
            foreach ($result['errors'] ?? [] as $e) {
                $io->writeln("  <error>tenant {$e['tenant_id']}: {$e['msg']}</error>");
            }
        }

        // 3. 验证 saas_tenant_plugin 是否批量更新
        $io->section('验证 md_saas_tenant_plugin');
        $updated = Db::connection(TenantConnectionManager::getDefaultConnectionName())->table('saas_tenant_plugin')
            ->where('plugin_key', $plugin)
            ->whereIn('tenant_id', $tenantIds)
            ->where('version', $toVersion)
            ->count();
        $io->info("version={$toVersion} 更新成功数: {$updated}/{$count}");

        if ($forceQueue || $count > 50) {
            $io->section('验证 md_saas_tenant_plugin_sync_jobs');
            $jobCnt = Db::connection(TenantConnectionManager::getDefaultConnectionName())->table('saas_tenant_plugin_sync_jobs')
                ->where('plugin_key', $plugin)
                ->where('created_at', '>=', $now)
                ->count();
            $io->info("本次写入的同步任务审计记录数: {$jobCnt}");
        }

        if ($governance) {
            $io->section('升级治理验证（平台开关 / 租户忽略）');

            $reset = function (int $allow, ?string $ignored) use ($plugin, $tenantIds, $fromVersion, $now): void {
                Db::connection(TenantConnectionManager::getDefaultConnectionName())->table('saas_tenant_plugin')
                    ->where('plugin_key', $plugin)
                    ->whereIn('tenant_id', $tenantIds)
                    ->update([
                        'allow_upgrade'   => $allow,
                        'ignored_version' => $ignored,
                        'version'         => $fromVersion,
                        'updated_at'      => time(),
                    ]);
            };

            $countOld = function () use ($plugin, $tenantIds, $fromVersion): int {
                return Db::connection(TenantConnectionManager::getDefaultConnectionName())->table('saas_tenant_plugin')
                    ->where('plugin_key', $plugin)
                    ->whereIn('tenant_id', $tenantIds)
                    ->where('version', $fromVersion)
                    ->count();
            };

            // 场景1: 平台禁止升级
            $reset(0, null);
            $svc->dispatch($plugin, $toVersion, 'update', $tenantIds, ['force' => true], null);
            $io->writeln("  场景1 平台禁止升级(allow_upgrade=0): 仍为 {$fromVersion} 的数量 = " . $countOld() . "/{$count} (期望 {$count})");

            // 场景2: 租户忽略该版本
            $reset(1, $toVersion);
            $svc->dispatch($plugin, $toVersion, 'update', $tenantIds, ['force' => true], null);
            $io->writeln("  场景2 租户忽略升级(ignored_version={$toVersion}): 仍为 {$fromVersion} 的数量 = " . $countOld() . "/{$count} (期望 {$count})");

            // 场景3: 恢复正常升级
            $reset(1, null);
            $svc->dispatch($plugin, $toVersion, 'update', $tenantIds, ['force' => true], null);
            $upgraded = Db::connection(TenantConnectionManager::getDefaultConnectionName())->table('saas_tenant_plugin')
                ->where('plugin_key', $plugin)
                ->whereIn('tenant_id', $tenantIds)
                ->where('version', $toVersion)
                ->count();
            $io->writeln("  场景3 正常升级(放开开关): version={$toVersion} 的数量 = {$upgraded}/{$count} (期望 {$count})");
        }

        if ($clean) {
            $io->section('清理测试数据');
            Db::connection(TenantConnectionManager::getDefaultConnectionName())->table('saas_tenant_plugin')
                ->where('plugin_key', $plugin)
                ->whereIn('tenant_id', $tenantIds)
                ->delete();
            if ($forceQueue || $count > 50) {
                Db::connection(TenantConnectionManager::getDefaultConnectionName())->table('saas_tenant_plugin_sync_jobs')
                    ->where('plugin_key', $plugin)
                    ->where('status', 'pending')
                    ->where('created_at', '>=', $now)
                    ->delete();
            }
            $io->success('已清理本次测试产生的记录');
        } else {
            $io->note('未清理。加 --clean 可删除本次产生的 saas_tenant_plugin / saas_tenant_plugin_sync_jobs 记录。');
        }

        return self::SUCCESS;
    }
}
