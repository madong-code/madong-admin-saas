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

namespace core\communication\mcp\command;

use core\communication\mcp\discovery\ToolManifest;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * madong-mcp:list：输出 MCP 工具清单（core 基类，经 app\command\mcp 薄子类注册）
 */
class McpListCommand extends Command
{
    protected function configure(): void
    {
        $this->addOption('rebuild', null, InputOption::VALUE_NONE, 'Force rebuild the tool manifest cache');
    }

    public function __invoke(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            $entries = (new ToolManifest())->load((bool) $input->getOption('rebuild'));
        } catch (\Throwable $e) {
            $io->error('Manifest load failed: ' . $e->getMessage());
            return Command::FAILURE;
        }

        if ($entries === []) {
            $io->warning('No MCP tools discovered');
            return Command::SUCCESS;
        }

        $rows = [];
        foreach ($entries as $entry) {
            $rows[] = [
                $entry['name'],
                self::permissionLabel($entry['permission']),
                $entry['class'] . '::' . $entry['method'],
                $entry['source'],
                $entry['description'],
            ];
        }

        $io->title(sprintf('MCP Tools (%d)', count($entries)));
        $io->table(['Name', 'Permission', 'Handler', 'Source', 'Description'], $rows);

        return Command::SUCCESS;
    }

    public static function permissionLabel(string|array|false|null $permission): string
    {
        return match (true) {
            $permission === null => 'public(匿名)',
            $permission === false => 'authenticated(需登录)',
            is_array($permission) => implode(' & ', $permission),
            default => (string) $permission,
        };
    }
}
