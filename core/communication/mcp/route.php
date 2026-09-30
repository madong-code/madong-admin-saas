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

use core\communication\mcp\endpoint\McpEndpointController;
use Webman\Route;

/**
 * MCP 端点路由
 *
 * 必须在主路由 config/route.php 的 Route::disableDefaultRoute() 之前 require；
 * Route::any 覆盖 MCP Streamable HTTP 的 POST(JSON-RPC) / GET(SSE 流) / DELETE(终止会话)；
 * 固定路径 /mcp（CoreConfigBootstrap 在路由之后运行，此处不可读 config()）。
 */
Route::any('/mcp', [McpEndpointController::class, 'handle']);
