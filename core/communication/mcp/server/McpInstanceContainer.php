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

namespace core\communication\mcp\server;

use Psr\Container\ContainerInterface;

/**
 * MCP 工具实例容器（只读映射）
 *
 * ServerFactory 逐请求实例化工具类（注入 McpUser）后，以类名为键提供给 SDK 的
 * ReferenceHandler：SDK 对 [class, method] 处理器优先从容器取实例，避免其自行
 * new 出无身份的工具对象。
 */
final class McpInstanceContainer implements ContainerInterface
{
    public function __construct(
        private readonly array $instances,
    ) {
    }

    public function get(string $id): object
    {
        if (!isset($this->instances[$id])) {
            throw new \OutOfRangeException(sprintf('MCP tool instance not registered: %s', $id));
        }
        return $this->instances[$id];
    }

    public function has(string $id): bool
    {
        return isset($this->instances[$id]);
    }
}
