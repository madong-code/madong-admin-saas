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
use app\enum\plugin\FrontendType;
use core\business\plugin\PluginPath;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * 删除插件（开发时清理，不含安装状态检查）
 *
 * @author Mr.April
 * @since 1.0.0
 */
#[AsCommand(
    name: 'madong-plugin:develop:delete',
    description: 'Delete plugin',
    aliases: ['madong-plugin:dev:delete'],
    hidden: false
)]
class DevelopDeleteCommand extends BaseCommand
{
    protected function configure(): void
    {
        $this->addArgument('key', InputArgument::REQUIRED, 'Plugin key (kebab-case format, e.g. test-demo)');
    }

    public function __invoke(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Delete Plugin');

        $pluginKey = $input->getArgument('key');
        $pluginName = str_replace('-', '_', $pluginKey);
        $projectRoot = dirname(base_path());

        $io->info(sprintf("Deleting plugin: %s", $pluginKey));

        try {
            // 检查插件是否已安装（避免删除运行中的插件）
            $installedFile = PluginPath::installedFlagPath($pluginName);
            if (is_file($installedFile)) {
                $io->warning("Plugin '{$pluginKey}' is still installed. Please uninstall first.");
                if (!$io->confirm('Force delete anyway? This may leave data behind.', false)) {
                    $io->note('Deletion cancelled');
                    return Command::SUCCESS;
                }
            }

            // 收集所有需删除的路径
            $paths = [];

            // 后端插件目录
            $backendPath = PluginPath::pluginRoot($pluginName);
            if (is_dir($backendPath)) {
                $paths[] = ['Backend directory', $backendPath];
            }

            // 多类型前端插件目录（admin / web）
            foreach (FrontendType::cases() as $type) {
                $frontendPath = $projectRoot . '/' . sprintf($type->pathTemplate(), $pluginName);
                if (is_dir($frontendPath)) {
                    $paths[] = ['Frontend (' . $type->value . ')', $frontendPath];
                }
            }

            if (empty($paths)) {
                $io->warning("No directories found for plugin '{$pluginKey}'");
                return Command::SUCCESS;
            }

            // 列出待删除路径
            $io->section('Paths to delete');
            foreach ($paths as [, $path]) {
                $io->writeln("  <comment>{$path}</comment>");
            }

            // 确认
            if (!$io->confirm('Are you sure you want to delete these directories?', false)) {
                $io->note('Deletion cancelled');
                return Command::SUCCESS;
            }

            // 执行删除
            foreach ($paths as [$label, $path]) {
                $this->deleteDirectory($path);
                $io->text(sprintf("Deleted %s: %s", $label, $path));
            }

            // 删除运行时目录
            $runtimePath = base_path() . '/runtime/plugins/' . $pluginKey;
            if (is_dir($runtimePath)) {
                $this->deleteDirectory($runtimePath);
                $io->text(sprintf("Deleted runtime directory: %s", $runtimePath));
            }

            // 删除运行时ZIP文件
            $runtimeZipPath = base_path() . '/runtime/plugins/' . $pluginKey . '.zip';
            if (file_exists($runtimeZipPath)) {
                unlink($runtimeZipPath);
                $io->text(sprintf("Deleted runtime ZIP file: %s", $runtimeZipPath));
            }

            // 删除构建目录
            $buildPath = base_path() . '/runtime/build/' . $pluginKey;
            if (is_dir($buildPath)) {
                $this->deleteDirectory($buildPath);
                $io->text(sprintf("Deleted build directory: %s", $buildPath));
            }

            return $this->outputSuccess($io, "Plugin deleted successfully!");
        } catch (\Exception $e) {
            return $this->outputError($io, sprintf("Deletion failed: %s", $e->getMessage()), $e);
        }
    }
}
