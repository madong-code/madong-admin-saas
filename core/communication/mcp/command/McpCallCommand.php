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
use core\communication\mcp\security\McpUser;
use core\communication\mcp\support\McpTenant;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * madong-mcp:call：CLI 直接调用 MCP 工具（绕过 SDK 传输层；CLI 身份视为超级管理员）
 *
 * 多租户下可用 --tenant 指定租户（字段隔离按 tenant_id 过滤，库隔离切租户库），
 * 缺省为平台级（不做租户过滤）；单体模式下 --tenant 无意义。
 */
class McpCallCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->addArgument('name', InputArgument::REQUIRED, 'Tool name (see madong-mcp:list)')
            ->addArgument('json', InputArgument::OPTIONAL, 'Arguments as JSON object, keys must match inputSchema property names', '{}')
            ->addOption('tenant', null, InputOption::VALUE_REQUIRED, 'Bind a tenant for this call (multi-tenant only; omit for platform level)');
    }

    public function __invoke(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $name = (string) $input->getArgument('name');

        try {
            $entries = (new ToolManifest())->load();
        } catch (\Throwable $e) {
            $io->error('Manifest load failed: ' . $e->getMessage());
            return Command::FAILURE;
        }

        $entry = null;
        foreach ($entries as $item) {
            if ($item['name'] === $name) {
                $entry = $item;
                break;
            }
        }
        if ($entry === null) {
            $io->error(sprintf('Tool "%s" not found. Available: %s', $name, implode(', ', array_column($entries, 'name'))));
            return Command::FAILURE;
        }

        $arguments = json_decode((string) $input->getArgument('json'), true);
        if (!is_array($arguments)) {
            $io->error('Arguments must be a valid JSON object');
            return Command::FAILURE;
        }

        $class = (string) $entry['class'];
        $method = (string) $entry['method'];
        $instance = new $class(new McpUser('cli', ['*'], [], 'cli'));

        $tenantId = $input->getOption('tenant');
        McpTenant::activate($tenantId === null ? null : (string) $tenantId);
        try {
            $result = $instance->{$method}(...$arguments);
        } catch (\Throwable $e) {
            $io->error(sprintf('%s: %s', $e::class, $e->getMessage()));
            return Command::FAILURE;
        } finally {
            McpTenant::deactivate();
        }

        $io->writeln(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return Command::SUCCESS;
    }
}
