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
use app\service\core\plugin\PluginUninstallService;
use support\Container;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * 插件卸载命令
 * 使用方法：
 * - 卸载插件：php webman madong-plugin:uninstall plugin-name
 *
 * @author Mr.April
 * @since 1.0.0
 */
#[AsCommand(
    name: 'madong-plugin:uninstall',
    description: 'Uninstall plugin from the system',
    aliases: ['madong-plugin:uninstall'],
    hidden: false
)]
class UninstallCommand extends BaseCommand
{
    /**
     * 配置命令
     */
    protected function configure(): void
    {
        $this->addArgument('name', InputArgument::REQUIRED, 'Plugin name')
            ->addOption(
                'template-mode',
                null,
                InputOption::VALUE_OPTIONAL,
                'Frontend template layout mode (mono/separated). Default from config/install.php',
                null
            )
            ->addOption('yes', 'y', InputOption::VALUE_NONE, 'Skip confirmation prompt');
    }

    /**
     * 执行命令
     */
    public function __invoke(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Plugin Uninstaller');

        // 获取参数
        $name = $input->getArgument('name');
        $templateMode = $input->getOption('template-mode');

        $io->info("Uninstalling plugin: {$name}");
        $io->info('Template mode: ' . ($templateMode ?: 'default(config/install.php)'));

        // 确认卸载（--yes 跳过确认，便于脚本/CI 自动化）
        if (!$input->getOption('yes') && !$this->confirm($io, "Are you sure you want to uninstall plugin {$name}? This action cannot be undone.")) {
            $io->note("Uninstallation cancelled");
            return Command::SUCCESS;
        }

        try {
            // 通过容器获取卸载服务实例（统一入口）
            $uninstallService = Container::get(PluginUninstallService::class);

            // 执行卸载流程（使用基类的流式处理）
            return $this->executeStream($uninstallService->uninstall($name, null, $templateMode), $io, $name);

        } catch (\Exception $e) {
            return $this->outputError($io, sprintf("Uninstallation failed: %s", $e->getMessage()), $e);
        }
    }
}
