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
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * 插件运行器命令
 *
 * 使用方法：
 *   php webman madong-plugin:run plugin-name install
 *   php webman madong-plugin:run plugin-name uninstall  # 使用统一的卸载服务
 *   php webman madong-plugin:run plugin-name delete     # 删除插件包
 *   php webman madong-plugin:run plugin-name update
 *   php webman madong-plugin:run plugin-name export
 *
 * 自动调用插件的 Install 类或对应的服务
 */
#[AsCommand(
    name: 'madong-plugin:run',
    description: 'Run plugin actions: install, uninstall, delete, update, export',
    aliases: ['madong-plugin:run'],
    hidden: false
)]
class RunCommand extends BaseCommand
{
    protected function configure(): void
    {
        $this->addArgument('name', InputArgument::REQUIRED, 'Plugin name')
            ->addArgument('action', InputArgument::REQUIRED, 'Action: install, uninstall, delete, update, export')
            ->addArgument('version', InputArgument::OPTIONAL, 'Version (for update)')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Force run migrations (ignore previous records)')
            ->addOption('data', 'd', InputOption::VALUE_NONE, 'Export with data (for export action)')
            ->addOption('output', 'o', InputOption::VALUE_OPTIONAL, 'Output file path (for export action)');
    }

    public function __invoke(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $name = $input->getArgument('name');
        $action = $input->getArgument('action');
        $version = $input->getArgument('version') ?? '1.0.0';
        $force = $input->getOption('force');

        $io->title("Plugin Runner: {$name}");
        $io->info("Action: {$action}" . ($force ? " (force)" : ""));

        // 查找插件路径
        $pluginPath = base_path('plugin/' . $name);

        if (!is_dir($pluginPath)) {
            $io->error("Plugin '{$name}' not found");
            return Command::FAILURE;
        }

        // 查找 Install 类（支持多个位置）
        $installFile = $pluginPath . '/Install.php';
        if (!file_exists($installFile)) {
            $installFile = $pluginPath . '/api/Install.php';
        }
        if (!file_exists($installFile)) {
            $installFile = $pluginPath . '/install/Install.php';
        }

        if (!file_exists($installFile)) {
            $io->error("Install.php not found");
            return Command::FAILURE;
        }

        // 加载 Install 类
        include $installFile;

        // 确定类名
        $className = "plugin\\{$name}\\Install";

        if (!class_exists($className)) {
            $io->error("Install class not found: {$className}");
            return Command::FAILURE;
        }

        $io->info("Found: {$className}");

        try {
            // 实例化并调用对应方法
            $instance = new $className();
            $io->info("Instance created: " . get_class($instance));

            switch ($action) {
                case 'install':
                    // Force migration: clear logs first
                    if ($force && method_exists($instance, 'forceMigrate')) {
                        $io->info('>>> Force migration mode - clearing logs...');
                        $instance->forceMigrate();
                    } elseif (!method_exists($instance, 'install')) {
                        $io->warning('install() method not found');
                        return Command::FAILURE;
                    } else {
                        $io->info('>>> Calling install() method...');
                        $result = $instance->install($version);
                        $io->info('<<< install() completed');
                    }
                    $io->success("Plugin '{$name}' installed successfully!");
                    break;

                case 'uninstall':
                    // 使用统一的卸载服务（会检查 undeletable 配置）
                    $io->info('>>> Calling uninstall via PluginUninstallService...');
                    $uninstallService = Container::get(PluginUninstallService::class);
                    return $this->executeStream($uninstallService->uninstall($name), $io, $name);

                case 'delete':
                    // 使用统一的删除服务（会检查卸载状态）
                    $io->info('>>> Calling delete via PluginUninstallService...');
                    $io->note("Plugin must be uninstalled before deletion");
                    $deleteService = Container::get(PluginUninstallService::class);
                    $deleteService->delete($name); // 失败会抛异常
                    $io->success("Plugin '{$name}' deleted successfully!");
                    return Command::SUCCESS;

                case 'update':
                    if (!method_exists($instance, 'update')) {
                        $io->warning('update() method not found');
                        return Command::FAILURE;
                    }
                    $oldVersion = $version;
                    $io->info(">>> Calling update() from {$oldVersion}...");
                    $result = $instance->update($oldVersion, $version);
                    $io->info('<<< update() completed');
                    $io->success("Plugin '{$name}' updated successfully!");
                    break;

                case 'export':
                    if (!method_exists($instance, 'exportSql')) {
                        $io->warning('exportSql() method not found');
                        return Command::FAILURE;
                    }
                    $withData = $input->getOption('data');
                    $outputFile = $input->getOption('output');
                    $io->info('>>> Exporting SQL' . ($withData ? ' (with data)' : ' (structure only)') . '...');
                    $result = $instance->exportSql($withData, $outputFile);
                    if ($result) {
                        $io->success("SQL exported successfully!");
                    } else {
                        $io->warning('Export failed or no tables found');
                    }
                    break;

                default:
                    $io->error("Unknown action: {$action}");
                    return Command::FAILURE;
            }

            return Command::SUCCESS;

        } catch (\Throwable $e) {
            return $this->outputError($io, "Error: " . $e->getMessage(), $e);
        }
    }
}
