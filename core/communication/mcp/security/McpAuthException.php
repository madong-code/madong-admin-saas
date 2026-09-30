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

namespace core\communication\mcp\security;

/**
 * MCP 鉴权失败异常（端点控制器捕获后转 HTTP 401 + JSON 错误体）
 */
final class McpAuthException extends \RuntimeException
{
}
