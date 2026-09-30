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
use core\business\plugin\PluginInstall;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

//命令	                                             说明
//php webman madong-plugin-migrate demo up	         执行 demo 插件所有待迁移
//php webman madong-plugin-migrate demo seed	     执行 demo 插件种子
//php webman madong-plugin-migrate demo rollback     回滚 demo 插件迁移（清除日志 + 删表）
//php webman madong-plugin-migrate demo status	     查看 demo 插件迁移状态

/**
 * 插件迁移管理
 * 复用 PluginInstall 的 runMigrations() / runSeeds()，无需重新实现
 *
 * @author Mr.April
 * @since  1.0
 */
#[AsCommand(
    name: 'madong-plugin-migrate',
    description: '插件迁移管理 (up/seed/rollback/status)',
)]
class PluginMigrateCommand extends BaseCommand
{
    protected function configure(): void
    {
        $this->addArgument('plugin', InputArgument::REQUIRED, '插件名称')
            ->addArgument('operate', InputArgument::REQUIRED, '操作: up/seed/rollback/status');
    }

    public function __invoke(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $pluginName = $input->getArgument('plugin');
        $operate    = $input->getArgument('operate');

        $pluginPath = base_path("plugin/{$pluginName}");
        if (!is_dir($pluginPath)) {
            $io->error("Plugin not found: {$pluginName}");
            return Command::INVALID;
        }

        $validOperations = ['up', 'seed', 'rollback', 'status'];
        if (!in_array($operate, $validOperations)) {
            $io->error("Invalid operation: {$operate} (up/seed/rollback/status)");
            return Command::INVALID;
        }

        // 复用 PluginInstall，通过匿名类重写 getPluginPath() 传入插件路径
        $installer = new class($pluginPath) extends PluginInstall
        {
            private string $customPluginPath;

            public function __construct(string $pluginPath)
            {
                $this->customPluginPath = $pluginPath;
                parent::__construct();
            }

            protected function getPluginPath(): string
            {
                return $this->customPluginPath;
            }
        };

        $installer->setContext('platform');

        match ($operate) {
            'up' => $this->runUp($io, $installer, $pluginName),
            'seed' => $this->runSeed($io, $installer, $pluginName),
            'rollback' => $this->runRollback($io, $installer, $pluginName),
            'status' => $this->runStatus($io, $pluginName),
        };

        return Command::SUCCESS;
    }

    private function runUp(SymfonyStyle $io, PluginInstall $installer, string $pluginName): void
    {
        $io->section("Running migrations for: {$pluginName}");
        $installer->runMigrations();
    }

    private function runSeed(SymfonyStyle $io, PluginInstall $installer, string $pluginName): void
    {
        $io->section("Running seeds for: {$pluginName}");
        $installer->runSeeds();
    }

    private function runRollback(SymfonyStyle $io, PluginInstall $installer, string $pluginName): void
    {
        $io->section("Rolling back migrations for: {$pluginName}");
        $installer->rollbackMigrations(true);
    }

    private function runStatus(SymfonyStyle $io, string $pluginName): void
    {
        $migrationsDir = base_path("plugin/{$pluginName}/resource/database/migrations");

        if (!is_dir($migrationsDir)) {
            $io->note("No migrations directory");
            return;
        }

        $files = glob("{$migrationsDir}/*.php");
        $logFile = runtime_path('migrations/plugin/' . $pluginName . '/migrations.log');
        $executed = [];
        if (file_exists($logFile)) {
            $content = trim(file_get_contents($logFile));
            foreach (explode(PHP_EOL, $content) as $line) {
                $line = trim($line);
                if (!empty($line) && !str_starts_with($line, '#')) {
                    $parts = explode(',', $line);
                    // 格式: batch,filename,status,... → 取 filename 去重
                    $executed[trim($parts[1] ?? '')] = true;
                }
            }
        }
        $executed = array_keys($executed);

        $total = count($files);
        $done = count($executed);
        $io->section("Plugin Migration Status: {$pluginName}");

        $io->writeln(sprintf(
            '<info>Migrated: %d | Pending: %d | Total: %d</info>',
            $done,
            $total - $done,
            $total
        ));

        foreach ($files as $file) {
            $basename = basename($file, '.php');
            if (in_array($basename, $executed)) {
                $io->writeln("<info>  ✅ {$basename}</info>");
            } else {
                $io->writeln("<comment>  ⏳ {$basename}</comment>");
            }
        }
    }
}
