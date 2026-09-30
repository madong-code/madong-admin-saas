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

namespace core\communication\mcp\tool\catalog;

use core\communication\mcp\attribute\McpTool;
use core\communication\mcp\command\McpListCommand;
use core\communication\mcp\discovery\ToolManifest;
use core\communication\mcp\security\McpUser;

/**
 * tool_catalog：MCP 工具自省（列出全部已注册工具与权限要求）
 */
final class ToolCatalogTool
{
    public function __construct(
        private readonly ?McpUser $user = null,
    ) {
    }

    #[McpTool(
        name: 'tool_catalog',
        title: 'Tool Catalog',
        description: '列出当前 madong MCP Server 已注册的全部工具（名称/描述/权限要求/来源目录），可按关键词过滤。',
        inputSchema: [
            'type' => 'object',
            'properties' => [
                'keyword' => [
                    'type' => 'string',
                    'description' => '按工具名/标题/描述过滤（不区分大小写，空=全部）',
                ],
            ],
        ],
        permission: false,
    )]
    public function toolCatalog(string $keyword = ''): array
    {
        $entries = (new ToolManifest())->load();
        $keyword = trim($keyword);

        $tools = [];
        foreach ($entries as $entry) {
            if ($keyword !== ''
                && stripos((string) $entry['name'], $keyword) === false
                && stripos((string) $entry['title'], $keyword) === false
                && stripos((string) $entry['description'], $keyword) === false) {
                continue;
            }
            $tools[] = [
                'name'        => $entry['name'],
                'title'       => $entry['title'],
                'description' => $entry['description'],
                'permission'  => McpListCommand::permissionLabel($entry['permission']),
                'source'      => $entry['source'],
            ];
        }

        return ['total' => count($tools), 'tools' => $tools];
    }
}
