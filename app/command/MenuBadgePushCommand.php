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

namespace app\command;

use app\command\BaseCommand;
use app\model\tenant\Tenant;
use core\business\tenant\context\TenantContext;
use core\business\tenant\TenantConnectionManager;
use core\communication\notify\MenuBadgePushService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * 菜单徽标推送（动态推送，验收 WebSocket 链路）
 *
 * 用法:
 *   php webman madong:menu-badge:push --tenant=1 --user=1 --path=/content/message --badge=5
 *   php webman madong:menu-badge:push --tenant=1 --user=1 --path=/content/message --clear
 *   php webman madong:menu-badge:push --tenant='*' --user=1 --reset     # 平台端(无租户)
 *
 * 说明: 频道规则与前端订阅一致
 *   admin    端: backend-admin-{tenantId}-{userId}
 *   platform 端: backend-platform-*-{userId}
 */
#[AsCommand(
    name: 'madong:menu-badge:push',
    description: '推送菜单徽标变更（WebSocket 实时更新）'
)]
class MenuBadgePushCommand extends BaseCommand
{
    protected function configure(): void
    {
        $this->addOption('tenant', 't', InputOption::VALUE_REQUIRED, "租户ID；平台端传 '*'", '*')
            ->addOption('user', 'u', InputOption::VALUE_REQUIRED, '用户ID（多个用逗号分隔）')
            ->addOption('path', 'p', InputOption::VALUE_REQUIRED, '菜单路径')
            ->addOption('badge', 'b', InputOption::VALUE_REQUIRED, '徽标文本', '')
            ->addOption('variant', null, InputOption::VALUE_REQUIRED, '徽标颜色(primary|success|warning|danger|destructive|default)', 'primary')
            ->addOption('type', null, InputOption::VALUE_REQUIRED, '徽标类型(normal|dot)', 'normal')
            ->addOption('clear', null, InputOption::VALUE_NONE, '清除指定菜单徽标')
            ->addOption('reset', null, InputOption::VALUE_NONE, '重置用户所有徽标');
    }

    public function __invoke(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('菜单徽标 · 动态推送');

        $tenant = (string)$input->getOption('tenant');
        $userIdRaw = (string)$input->getOption('user');
        $path = (string)$input->getOption('path');

        if ($userIdRaw === '') {
            $io->error('缺少必要参数 --user');

            return self::FAILURE;
        }

        $isReset = (bool)$input->getOption('reset');
        $isClear = (bool)$input->getOption('clear');
        if (!$isReset && $path === '') {
            $io->error('缺少必要参数 --path（--reset 时无需）');

            return self::FAILURE;
        }

        $userIds = array_values(array_filter(array_map('trim', explode(',', $userIdRaw)), 'strlen'));

        $io->writeln("   租户: {$tenant}");
        $io->writeln('   用户: ' . implode(',', $userIds));

        $service = new MenuBadgePushService();
        $manageTenantContext = $tenant !== '' && $tenant !== '*' && ctype_digit($tenant);

        try {
            // 显式建立租户上下文（项目硬约束）；平台端 '*' 无租户，跳过。
            // 按隔离模式处理（对齐 TenantMiddleware）：field 走主库，database 切换租户库连接。
            if ($manageTenantContext) {
                TenantContext::setTenant($tenant);
                $tenantInfo = TenantContext::getTenantInfo();
                if (($tenantInfo['database_mode'] ?? '') === Tenant::MODE_DATABASE) {
                    TenantConnectionManager::setCurrentConnection($tenant, false);
                } else {
                    TenantContext::setIsolationMode('field');
                }
            }

            if ($isReset) {
                $count = $service->pushBadgeReset($tenant, $userIds);
                $io->writeln('   动作: reset（重置全部徽标）');
            } elseif ($isClear) {
                $count = $service->pushBadgeClear($tenant, $userIds, $path);
                $io->writeln("   动作: clear（清除 {$path}）");
            } else {
                $badge = (string)$input->getOption('badge');
                $variant = (string)$input->getOption('variant');
                $type = (string)$input->getOption('type');
                $count = $service->pushBadgeUpdate($tenant, $userIds, $path, $badge, $variant, $type);
                $io->writeln("   动作: update（{$path} → badge={$badge} type={$type} variant={$variant}）");
            }

            $io->success("推送完成，命中频道数: {$count}");

            return self::SUCCESS;
        } catch (\Throwable $e) {
            return $this->outputError($io, '推送失败: ' . $e->getMessage(), $e);
        } finally {
            if ($manageTenantContext) {
                TenantConnectionManager::releaseCurrentConnection();
            }
        }
    }
}