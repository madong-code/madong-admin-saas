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

namespace app\command\plugin;

use app\command\BaseCommand;
use app\service\admin\plugin\TenantPluginService;
use core\business\tenant\SyncConnection;
use core\io\uuid\Snowflake;
use support\Container;
use support\Db;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * 平台管理租户插件命令
 *
 * 说明：
 *   - 部署后一般无法直接使用脚本（生产环境无 CLI 访问），本命令作为运维/排查备用。
 *   - 授权/取消授权直接操作 saas_tenant_plugin（绕过模型回收站钩子，兼容 CLI 无 HTTP 请求）。
 *   - 安装/卸载/更新复用 TenantPluginService（SSE 生成器经 BaseCommand::executeStream 输出）。
 *
 * 使用方法：
 *   php webman madong-plugin:tenant list <tenant-id>
 *   php webman madong-plugin:tenant auth <tenant-id> <plugin>[,<plugin>...]
 *   php webman madong-plugin:tenant revoke <tenant-id> <plugin>[,<plugin>...]
 *   php webman madong-plugin:tenant install <tenant-id> <plugin> [--to-version=1.0.0]
 *   php webman madong-plugin:tenant uninstall <tenant-id> <plugin>
 *   php webman madong-plugin:tenant update <tenant-id> <plugin> [--to-version=1.0.1]
 *
 * @author Mr.April
 * @since  1.0.0
 */
#[AsCommand(
    name: 'madong-plugin:tenant',
    description: 'Platform manage tenant plugins (list/auth/revoke/install/uninstall/update)',
    aliases: ['madong-plugin:tenant'],
    hidden: false
)]
class TenantPluginManageCommand extends BaseCommand
{
    /** 合法 action 列表 */
    private const ACTIONS = ['list', 'auth', 'revoke', 'install', 'uninstall', 'update'];

    protected function configure(): void
    {
        $this->addArgument('action', InputArgument::REQUIRED, 'Action: list/auth/revoke/install/uninstall/update')
            ->addArgument('tenant', InputArgument::REQUIRED, 'Tenant ID')
            ->addArgument('plugin', InputArgument::OPTIONAL, 'Plugin key (comma separated for auth/revoke)')
            ->addOption('to-version', null, InputOption::VALUE_OPTIONAL, 'Target version (install/update)', null)
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Skip confirmation');
    }

    public function __invoke(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $action = $input->getArgument('action');
        $tenantId = (string)$input->getArgument('tenant');
        $pluginRaw = (string)($input->getArgument('plugin') ?? '');
        $version = $input->getOption('to-version');
        $force = (bool)$input->getOption('force');

        if (!in_array($action, self::ACTIONS, true)) {
            $io->error("Invalid action: {$action} (allowed: " . implode('/', self::ACTIONS) . ")");
            return self::FAILURE;
        }

        $io->title("Tenant Plugin Manager — {$action}");

        // 校验租户存在
        $tenant = \app\model\tenant\Tenant::withoutGlobalScopes()->find($tenantId);
        if (!$tenant) {
            $io->error("Tenant not found: {$tenantId}");
            return self::FAILURE;
        }
        $mode = $tenant->database_mode ?? 'field';
        $conn = SyncConnection::getConnectionName($tenantId, $mode);
        $io->info(sprintf("Tenant: %s (%s, mode=%s, conn=%s)", $tenantId, $tenant->name ?? '', $mode, $conn));

        try {
            return match ($action) {
                'list'    => $this->actionList($io, $tenantId, $conn),
                'auth'    => $this->actionAuth($io, $tenantId, $pluginRaw, $conn),
                'revoke'  => $this->actionRevoke($io, $tenantId, $pluginRaw, $force, $conn),
                'install' => $this->actionInstall($io, $tenantId, $pluginRaw, $version),
                'uninstall' => $this->actionUninstall($io, $tenantId, $pluginRaw, $force),
                'update'  => $this->actionUpdate($io, $tenantId, $pluginRaw, $version),
            };
        } catch (\Throwable $e) {
            return $this->outputError($io, $e->getMessage(), $e);
        }
    }

    /**
     * 列出租户插件授权/安装状态
     */
    private function actionList(SymfonyStyle $io, string $tenantId, string $conn): int
    {
        $rows = Db::connection($conn)->table('saas_tenant_plugin_install')
            ->where('tenant_id', $tenantId)
            ->orderBy('plugin_key')
            ->get()
            ->toArray();
        // 授权状态来自授权治理表
        $authMap = Db::connection($conn)->table('saas_tenant_plugin')
            ->where('tenant_id', $tenantId)
            ->pluck('auth_status', 'plugin_key')
            ->toArray();

        if (empty($rows)) {
            $io->warning('No tenant plugin records. Use auth to grant plugin authorization.');
            return self::SUCCESS;
        }

        $tableRows = [];
        foreach ($rows as $r) {
            $tableRows[] = [
                $r->plugin_key,
                $authMap[$r->plugin_key] ?? 'none',
                (int)$r->status === 1 ? '<info>1</info>' : '0',
                var_export($r->installed_at, true),
                var_export($r->version, true),
                $r->isolation_mode ?? '',
            ];
        }
        $io->table(
            ['Plugin', 'Auth', 'Status', 'InstalledAt', 'Version', 'Isolation'],
            $tableRows
        );
        $io->note('auth=authorized 表示平台已授权；status=1 且 installed_at 非空表示已安装');
        return self::SUCCESS;
    }

    /**
     * 授权插件给租户（直接写 saas_tenant_plugin，兼容 CLI 无 HTTP 请求）
     */
    private function actionAuth(SymfonyStyle $io, string $tenantId, string $pluginRaw, string $conn): int
    {
        $plugins = $this->parsePlugins($pluginRaw);
        if (empty($plugins)) {
            $io->error('Please provide at least one plugin key');
            return self::FAILURE;
        }

        $now = time();
        $granted = 0;
        foreach ($plugins as $key) {
            if (!is_dir(base_path('plugin/' . $key))) {
                $io->warning("Plugin not found on platform: {$key}, skipped");
                continue;
            }
            $exists = Db::connection($conn)->table('saas_tenant_plugin')
                ->where('tenant_id', $tenantId)
                ->where('plugin_key', $key)
                ->first();
            if ($exists) {
                Db::connection($conn)->table('saas_tenant_plugin')->where('id', $exists->id)->update([
                    'auth_status' => 'authorized',
                    'is_purchased' => 0,
                    'updated_at' => $now,
                ]);
                $io->text("  updated  {$key} (auth=authorized)");
            } else {
                Db::connection($conn)->table('saas_tenant_plugin')->insert([
                    'id'          => Snowflake::generate(),
                    'tenant_id'   => $tenantId,
                    'plugin_key'  => $key,
                    'auth_status' => 'authorized',
                    'is_purchased' => 0,
                    'created_at'  => $now,
                    'updated_at'  => $now,
                ]);
                $io->text("  inserted {$key} (auth=authorized)");
            }
            // 确保运行态记录存在(授权≠安装, 默认未安装态)
            Db::connection($conn)->table('saas_tenant_plugin_install')->updateOrInsert(
                ['tenant_id' => $tenantId, 'plugin_key' => $key],
                [
                    'id'          => Snowflake::generate(),
                    'tenant_id'   => $tenantId,
                    'plugin_key'  => $key,
                    'status'      => 0,
                    'sync_status' => 'pending',
                    'created_at'  => $now,
                    'updated_at'  => $now,
                ]
            );
            $granted++;
        }

        $io->success("Authorized {$granted} plugin(s) to tenant {$tenantId}");
        return self::SUCCESS;
    }

    /**
     * 取消授权（仅删除授权记录，不影响已安装菜单/表；已安装时需 --force 确认）
     */
    private function actionRevoke(SymfonyStyle $io, string $tenantId, string $pluginRaw, bool $force, string $conn): int
    {
        $plugins = $this->parsePlugins($pluginRaw);
        if (empty($plugins)) {
            $io->error('Please provide at least one plugin key');
            return self::FAILURE;
        }

        foreach ($plugins as $key) {
            $record = Db::connection($conn)->table('saas_tenant_plugin')
                ->where('tenant_id', $tenantId)
                ->where('plugin_key', $key)
                ->first();
            if (!$record) {
                $io->text("  no record: {$key}, skipped");
                continue;
            }

            $isInstalled = (int)$record->status === 1 && !empty($record->installed_at);
            if ($isInstalled && !$force) {
                $io->warning("{$key} is still installed on tenant. Use --force to revoke authorization anyway.");
                continue;
            }

            Db::connection($conn)->table('saas_tenant_plugin_install')->where('tenant_id', $tenantId)->where('plugin_key', $key)->delete();
            Db::connection($conn)->table('saas_tenant_plugin')->where('id', $record->id)->delete();
            $io->text("  revoked   {$key}");
        }

        $io->success('Revoke complete');
        return self::SUCCESS;
    }

    /**
     * 安装插件到租户（复用 TenantPluginService::install SSE 生成器）
     */
    private function actionInstall(SymfonyStyle $io, string $tenantId, string $pluginRaw, ?string $version): int
    {
        $key = trim($pluginRaw, " ,\t\n\r\0\x0B");
        if ($key === '') {
            $io->error('Please provide a plugin key');
            return self::FAILURE;
        }

        /** @var TenantPluginService $svc */
        $svc = Container::make(TenantPluginService::class);
        return $this->executeStream($svc->install($key, $tenantId), $io, $key);
    }

    /**
     * 卸载租户插件（复用 TenantPluginService::uninstall SSE 生成器）
     */
    private function actionUninstall(SymfonyStyle $io, string $tenantId, string $pluginRaw, bool $force): int
    {
        $key = trim($pluginRaw, " ,\t\n\r\0\x0B");
        if ($key === '') {
            $io->error('Please provide a plugin key');
            return self::FAILURE;
        }

        if (!$force && !$io->confirm("Uninstall plugin '{$key}' from tenant {$tenantId}?", false)) {
            $io->note('Cancelled');
            return self::SUCCESS;
        }

        /** @var TenantPluginService $svc */
        $svc = Container::make(TenantPluginService::class);
        return $this->executeStream($svc->uninstall($key, $tenantId), $io, $key);
    }

    /**
     * 更新租户插件（复用 TenantPluginService::update，非流式）
     */
    private function actionUpdate(SymfonyStyle $io, string $tenantId, string $pluginRaw, ?string $version): int
    {
        $key = trim($pluginRaw, " ,\t\n\r\0\x0B");
        if ($key === '') {
            $io->error('Please provide a plugin key');
            return self::FAILURE;
        }

        /** @var TenantPluginService $svc */
        $svc = Container::make(TenantPluginService::class);

        if ($version === null) {
            // 未指定版本时，读取平台当前版本
            $infoFile = base_path('plugin/' . $key . '/config/info.php');
            $info = is_file($infoFile) ? (include $infoFile) : [];
            $version = (string)($info['version'] ?? '1.0.0');
        }

        $result = $svc->update($key, $tenantId, $version);
        if (!empty($result['skipped'])) {
            $io->note('Skipped: ' . ($result['reason'] ?? 'up-to-date'));
            return self::SUCCESS;
        }
        $io->success(sprintf(
            "Updated %s to v%s (from %s)",
            $key,
            $result['version'] ?? $version,
            $result['from'] ?? '?'
        ));
        return self::SUCCESS;
    }

    /**
     * 解析插件参数（支持逗号分隔 / 空格分隔）
     */
    private function parsePlugins(string $raw): array
    {
        $list = preg_split('/[\s,]+/', trim($raw)) ?: [];
        return array_values(array_filter(array_map('trim', $list), fn($v) => $v !== ''));
    }
}
