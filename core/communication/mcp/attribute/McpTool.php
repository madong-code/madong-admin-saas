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

namespace core\communication\mcp\attribute;

/**
 * MCP 工具声明属性（标注在工具类的方法上）
 *
 * 处理器方法参数名必须与 inputSchema.properties 键一致，
 * SDK 按反射以命名参数注入调用入参；McpUser 经工具类构造器注入，方法内禁止读全局 request()。
 */
#[\Attribute(\Attribute::TARGET_METHOD)]
final class McpTool
{
    /**
     * @param string                    $name        工具名（MCP tools/call 的 name，全局唯一）
     * @param string                    $title       UI 展示名（可选）
     * @param string                    $description 工具描述（供 LLM 理解用途）
     * @param array                     $inputSchema JSON Schema（draft 2020-12 兼容），显式声明保持 SDK 无关
     * @param string|array|false|null   $permission  权限控制：null=匿名可访问；false=仅需登录不要求权限码；
     *                                               string|array=需满足的菜单权限码（数组为 and 语义）
     */
    public function __construct(
        public string $name,
        public string $title = '',
        public string $description = '',
        public array $inputSchema = ['type' => 'object', 'properties' => []],
        public string|array|false|null $permission = null,
    ) {
    }
}
