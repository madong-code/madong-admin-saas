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
use app\service\core\plugin\PluginInstallService;
use support\Container;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * 插件安装命令
 * 使用方法：
 * - 安装插件：php webman madong-plugin:install plugin-name
 * - 从市场安装：php webman madong-plugin:install plugin-name market
 *
 * @author Mr.April
 * @since 1.0.0
 */
#[AsCommand(
    name: 'madong-plugin:install',
    description: 'Install plugin to the system',
    aliases: ['madong-plugin:install'],
    hidden: false
)]
class InstallCommand extends BaseCommand
{
    /**
     * 配置命令
     */
    protected function configure(): void
    {
        $this->addArgument('name', InputArgument::REQUIRED, 'Plugin name')
            ->addArgument('mode', InputArgument::OPTIONAL, 'Install mode (local/market)', 'local');
    }

    /**
     * 执行命令
     */
    public function __invoke(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Plugin Installer');

        // 获取参数
        $name = $input->getArgument('name');
        $mode = $input->getArgument('mode');

        $io->info("Installing plugin: {$name}");
        $io->info("Install mode: {$mode}");

        try {
            // 通过容器获取安装服务实例
            $installService = Container::get(PluginInstallService::class);

            // 执行安装流程（使用基类的流式处理）
            return $this->executeStream($installService->install($name, $mode), $io, $name);

        } catch (\Exception $e) {
            return $this->outputError($io, sprintf("Installation failed: %s", $e->getMessage()), $e);
        }
    }
}
