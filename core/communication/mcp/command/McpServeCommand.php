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
use core\communication\mcp\server\ServerFactory;
use Mcp\Server\Transport\StdioTransport;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use support\Log;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * madong-mcp:serve：STDIO 传输启动 MCP Server（供 MCP Inspector 等本地调试）
 *
 * CLI 身份视为超级管理员（本机可信，清单不做权限过滤）。
 * 受限平台：Windows 控制台（无阻塞 STDIN 支持不稳定），建议 Linux/macOS 或改用 HTTP 端点。
 * 注意：STDOUT 承载 MCP 协议帧，启动信息只能写 STDERR。
 */
class McpServeCommand extends Command
{
    public function __invoke(InputInterface $input, OutputInterface $output): int
    {
        $logger = self::logger();

        fwrite(STDERR, "madong MCP STDIO server starting...\n");

        try {
            $tools = (new ToolManifest())->load();
            $server = (new ServerFactory())->build($tools, new McpUser('cli', ['*'], [], 'cli'), $logger);
            $server->run(new StdioTransport(logger: $logger));
        } catch (\Throwable $e) {
            fwrite(STDERR, sprintf("MCP serve error: %s: %s\n", $e::class, $e->getMessage()));
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    private static function logger(): LoggerInterface
    {
        try {
            return Log::channel('default');
        } catch (\Throwable) {
            return new NullLogger();
        }
    }
}
