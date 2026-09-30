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
 * madong-mcp:call（webman/console 仅发现 app/command 下的命令，core 基类经此薄子类注册）
 */
#[AsCommand(
    name: 'madong-mcp:call',
    description: 'Call an MCP tool directly by name with JSON arguments (CLI runs as super admin)'
)]
class McpCallCommand extends \core\communication\mcp\command\McpCallCommand
{
}
