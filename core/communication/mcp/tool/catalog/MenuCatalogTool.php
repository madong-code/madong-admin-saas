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
use core\communication\mcp\security\McpUser;
use core\communication\mcp\support\McpTenant;

/**
 * menu_catalog：菜单与权限码清单（sys_menu，裸表名查询，不依赖 app）
 */
final class MenuCatalogTool
{
    public function __construct(
        private readonly ?McpUser $user = null,
    ) {
    }

    #[McpTool(
        name: 'menu_catalog',
        title: 'Menu Catalog',
        description: '查询 madong 菜单与权限码清单（sys_menu）。type=1 目录 / 2 菜单 / 3 按钮权限（权限码所在类型）；开发插件工具时用本工具选取真实权限码。',
        inputSchema: [
            'type' => 'object',
            'properties' => [
                'keyword' => [
                    'type' => 'string',
                    'description' => '按标题/权限码模糊过滤（空=全部）',
                ],
                'type' => [
                    'type' => 'integer',
                    'enum' => [0, 1, 2, 3],
                    'default' => 0,
                    'description' => '菜单类型，0=全部，3=按钮权限（权限码所在类型）',
                ],
                'limit' => [
                    'type' => 'integer',
                    'default' => 200,
                    'maximum' => 1000,
                    'description' => '返回条数上限',
                ],
            ],
        ],
        permission: false,
    )]
    public function menuCatalog(string $keyword = '', int $type = 0, int $limit = 200): array
    {
        $limit   = min(1000, max(1, $limit));
        $keyword = trim($keyword);

        $query = McpTenant::table('sys_menu')->whereNull('deleted_at');
        if ($type > 0) {
            $query->where('type', $type);
        }
        if ($keyword !== '') {
            $query->where(function ($q) use ($keyword): void {
                $q->where('title', 'like', '%' . $keyword . '%')
                  ->orWhere('code', 'like', '%' . $keyword . '%');
            });
        }

        $rows = $query->orderBy('sort', 'asc')->limit($limit)->get([
            'id', 'pid', 'app', 'title', 'code', 'type', 'path', 'is_show',
        ])->toArray();

        return ['total' => count($rows), 'menus' => $rows];
    }
}
