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
 * dict_catalog：业务字典查询（sys_dict / sys_dict_item，裸表名查询，不依赖 app）
 */
final class DictCatalogTool
{
    public function __construct(
        private readonly ?McpUser $user = null,
    ) {
    }

    #[McpTool(
        name: 'dict_catalog',
        title: 'Dict Catalog',
        description: '查询 madong 业务字典（sys_dict 及其枚举项 sys_dict_item），用于理解状态码/枚举值的业务含义。',
        inputSchema: [
            'type' => 'object',
            'properties' => [
                'keyword' => [
                    'type' => 'string',
                    'description' => '按字典编码/名称/描述模糊过滤（空=全部）',
                ],
                'limit' => [
                    'type' => 'integer',
                    'default' => 100,
                    'maximum' => 500,
                    'description' => '返回字典组数上限',
                ],
            ],
        ],
        permission: false,
    )]
    public function dictCatalog(string $keyword = '', int $limit = 100): array
    {
        $limit   = min(500, max(1, $limit));
        $keyword = trim($keyword);

        $query = McpTenant::table('sys_dict')->where('enabled', 1);
        if ($keyword !== '') {
            $query->where(function ($q) use ($keyword): void {
                $q->where('code', 'like', '%' . $keyword . '%')
                  ->orWhere('name', 'like', '%' . $keyword . '%')
                  ->orWhere('description', 'like', '%' . $keyword . '%');
            });
        }

        $dicts = $query->orderBy('sort', 'asc')->limit($limit)->get([
            'id', 'group_code', 'name', 'code', 'description',
        ])->toArray();
        if ($dicts === []) {
            return ['total' => 0, 'dicts' => []];
        }

        $dictIds = array_map(static fn ($d) => $d->id, $dicts);
        $items   = McpTenant::table('sys_dict_item')
            ->where('enabled', 1)
            ->whereIn('dict_id', $dictIds)
            ->orderBy('sort', 'asc')
            ->get(['dict_id', 'label', 'value', 'code', 'sort'])
            ->toArray();

        $grouped = [];
        foreach ($items as $item) {
            $grouped[$item->dict_id][] = [
                'label' => $item->label,
                'value' => $item->value,
                'code'  => $item->code,
                'sort'  => $item->sort,
            ];
        }

        $result = [];
        foreach ($dicts as $dict) {
            $result[] = [
                'code'        => $dict->code,
                'name'        => $dict->name,
                'group_code'  => $dict->group_code,
                'description' => $dict->description,
                'items'       => $grouped[$dict->id] ?? [],
            ];
        }

        return ['total' => count($result), 'dicts' => $result];
    }
}
