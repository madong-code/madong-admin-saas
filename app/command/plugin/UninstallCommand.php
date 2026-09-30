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
        $this->addArgument('name', InputArgument::REQUIRED, 'Plugin name');
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

        $io->info("Uninstalling plugin: {$name}");

        // 确认卸载
        if (!$this->confirm($io, "Are you sure you want to uninstall plugin {$name}? This action cannot be undone.")) {
            $io->note("Uninstallation cancelled");
            return Command::SUCCESS;
        }

        try {
            // 通过容器获取卸载服务实例（统一入口）
            $uninstallService = Container::get(PluginUninstallService::class);

            // 执行卸载流程（使用基类的流式处理）
            return $this->executeStream($uninstallService->uninstall($name), $io, $name);

        } catch (\Exception $e) {
            return $this->outputError($io, sprintf("Uninstallation failed: %s", $e->getMessage()), $e);
        }
    }
}
