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

namespace app\command\mcp;

use Symfony\Component\Console\Attribute\AsCommand;

/**
 * madong-mcp:serve（webman/console 仅发现 app/command 下的命令，core 基类经此薄子类注册）
 */
#[AsCommand(
    name: 'madong-mcp:serve',
    description: 'Start the MCP server over STDIO transport (restricted on Windows)'
)]
class McpServeCommand extends \core\communication\mcp\command\McpServeCommand
{
}
