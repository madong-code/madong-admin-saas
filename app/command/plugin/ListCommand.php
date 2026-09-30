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
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'madong-plugin:list',
    description: 'List all plugins and their status',
    aliases: ['madong-plugin:list'],
    hidden: false
)]
class ListCommand extends BaseCommand
{
    public function __invoke(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Plugin List');

        $pluginDir = base_path('plugin');
        if (!is_dir($pluginDir)) {
            $io->warning('No plugins directory found');
            return self::SUCCESS;
        }

        $plugins = [];
        $iterator = new \DirectoryIterator($pluginDir);

        foreach ($iterator as $item) {
            if (!$item->isDir() || $item->isDot()) {
                continue;
            }

            $pluginName = $item->getFilename();
            $configPath = $pluginDir . '/' . $pluginName . '/config/app.php';
            $installedPath = $pluginDir . '/' . $pluginName . '/config/installed.php';

            $config = [];
            if (file_exists($configPath)) {
                $config = require $configPath;
            }

            $installed = file_exists($installedPath);
            $installedAt = '';
            if ($installed) {
                $installedConfig = require $installedPath;
                $installedAt = $installedConfig['installed_at'] ?? '';
            }
            $title = $config['title'] ?? $pluginName;
            $version = $config['version'] ?? 'unknown';

            $plugins[] = [
                'name' => $pluginName,
                'title' => $title,
                'version' => $version,
                'installed' => $installed,
                'installed_at' => $installedAt,
            ];
        }

        if (empty($plugins)) {
            $io->warning('No plugins found');
            return self::SUCCESS;
        }

        $installedCount = count(array_filter($plugins, fn($p) => $p['installed']));
        $uninstalledCount = count($plugins) - $installedCount;

        $io->section(sprintf('总数: %d (已安装: %d, 未安装: %d)', count($plugins), $installedCount, $uninstalledCount));

        $tableRows = [];
        foreach ($plugins as $plugin) {
            $installStatus = $plugin['installed'] ? '<info>✓ 已安装</info>' : '<comment>○ 未安装</comment>';
            $installedAt = $plugin['installed_at'] ?: '-';
            $tableRows[] = [
                $plugin['name'],
                $plugin['title'],
                $plugin['version'],
                $installStatus,
                $installedAt,
            ];
        }

        $io->table(['Name', 'Title', 'Version', 'Status', 'Installed At'], $tableRows);

        $io->note('Use "php webman madong-plugin:install <plugin-name>" to install a plugin');

        return self::SUCCESS;
    }
}