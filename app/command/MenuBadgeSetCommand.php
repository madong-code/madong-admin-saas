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
use app\model\system\menu\Menu;
use app\model\tenant\PlatformMenu;
use app\model\tenant\Tenant;
use core\business\tenant\context\TenantContext;
use core\business\tenant\TenantConnectionManager;
use core\foundation\tool\MenuVariableParser;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * 菜单静态徽标写入（写入 menu.variable 根级 badge 域，验收 variable 解析链路）
 *
 * 用法:
 *   php webman madong:menu-badge:set --tenant=1 --path=/content/message --badge=2 --variant=warning
 *   php webman madong:menu-badge:set --tenant=1 --path=/content/message --clear
 *   php webman madong:menu-badge:set --app=platform --path=/tenant/list --badge=新 --variant=danger
 *
 * 说明: 徽标写入 variable 根级 badge 域（不新增数据库列），域之间互不干扰。
 */
#[AsCommand(
    name: 'madong:menu-badge:set',
    description: '写入/清除菜单静态徽标（menu.variable 的 badge 域）'
)]
class MenuBadgeSetCommand extends BaseCommand
{
    protected function configure(): void
    {
        $this->addOption('tenant', 't', InputOption::VALUE_REQUIRED, '租户ID（--app=platform 时忽略）', '*')
            ->addOption('app', null, InputOption::VALUE_REQUIRED, "菜单归属: admin|platform", 'admin')
            ->addOption('path', 'p', InputOption::VALUE_REQUIRED, '菜单路径')
            ->addOption('badge', 'b', InputOption::VALUE_REQUIRED, '徽标文本', '')
            ->addOption('variant', null, InputOption::VALUE_REQUIRED, '徽标颜色(primary|success|warning|danger|destructive|default)', 'primary')
            ->addOption('type', null, InputOption::VALUE_REQUIRED, '徽标类型(normal|dot)', 'normal')
            ->addOption('clear', null, InputOption::VALUE_NONE, '移除该菜单的 badge 域');
    }

    public function __invoke(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('菜单徽标 · 静态写入');

        $app = (string)$input->getOption('app');
        $tenant = (string)$input->getOption('tenant');
        $path = (string)$input->getOption('path');
        $isClear = (bool)$input->getOption('clear');

        if ($path === '') {
            $io->error('缺少必要参数 --path');

            return self::FAILURE;
        }

        $text = (string)$input->getOption('badge');
        $type = (string)$input->getOption('type');
        $variants = (string)$input->getOption('variant');

        $isPlatform = $app === 'platform';
        $manageTenantContext = !$isPlatform && $tenant !== '' && $tenant !== '*' && ctype_digit($tenant);

        try {
            if ($manageTenantContext) {
                // 按隔离模式建立上下文（对齐 TenantMiddleware）：
                // field 模式仅注入上下文走主库；database 模式切换租户库连接
                TenantContext::setTenant($tenant);
                $tenantInfo = TenantContext::getTenantInfo();
                if (($tenantInfo['database_mode'] ?? '') === Tenant::MODE_DATABASE) {
                    TenantConnectionManager::setCurrentConnection($tenant, false);
                } else {
                    TenantContext::setIsolationMode('field');
                }
            }

            $menu = $isPlatform
                ? PlatformMenu::where('app', 'platform')->where('path', $path)->first()
                : Menu::where('path', $path)->first();

            if (!$menu) {
                $io->error("未找到菜单: {$path}（app={$app}）");

                return self::FAILURE;
            }

            $variable = $isClear
                ? MenuVariableParser::withDomain($menu->variable ?? '', 'badge', null)
                : MenuVariableParser::withBadge($menu->variable ?? '', $text, $type, $variants);

            $menu->variable = $variable;
            $menu->save();

            $io->writeln("   菜单: {$menu->title}（id={$menu->id}, path={$menu->path}）");
            $io->writeln('   variable: ' . ($variable === '' ? '(空)' : $variable));
            $io->success($isClear ? 'badge 域已移除' : '静态徽标已写入');

            return self::SUCCESS;
        } catch (\Throwable $e) {
            return $this->outputError($io, '写入失败: ' . $e->getMessage(), $e);
        } finally {
            if ($manageTenantContext) {
                TenantConnectionManager::releaseCurrentConnection();
            }
        }
    }
}