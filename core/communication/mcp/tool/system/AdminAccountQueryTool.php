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

namespace core\communication\mcp\tool\system;

use core\communication\mcp\attribute\McpTool;
use core\communication\mcp\security\McpUser;
use core\communication\mcp\support\McpTenant;

/**
 * admin_account_query：管理员账号查询（权限码 mcp:admin:query）
 *
 * 安全约束：
 *  - SELECT 白名单字段，永不返回 password / tel 等敏感字段
 *  - 支持 id / user_name / real_name / nick_name 过滤
 *  - 最多返回 50 条
 */
final class AdminAccountQueryTool
{
    /** 安全白名单（除了这些字段，其余一律不返回） */
    private const SAFE_FIELDS = [
        'id', 'user_name', 'real_name', 'nick_name', 'is_super',
        'sex', 'birthday', 'remark', 'is_locked', 'created_at', 'updated_at',
    ];

    private const MAX_LIMIT = 50;

    public function __construct(
        private readonly ?McpUser $user = null,
    ) {
    }

    #[McpTool(
        name: 'admin_account_query',
        title: 'Admin Account Query',
        description: '查询管理员账号（sys_admin），仅返回非敏感字段（不含 password、tel、mobile_phone 等）。支持按 id / user_name / real_name / nick_name 过滤。需要权限码 mcp:admin:query。',
        inputSchema: [
            'type'       => 'object',
            'properties' => [
                'id'         => ['type' => 'integer', 'description' => '管理员 ID'],
                'user_name'  => ['type' => 'string', 'description' => '登录账号（精确匹配）'],
                'keyword'    => ['type' => 'string', 'description' => '模糊搜索 real_name / nick_name / user_name'],
                'limit'      => ['type' => 'integer', 'description' => '返回条数，默认 20，最大 50'],
            ],
        ],
        permission: 'mcp:admin:query',
    )]
    public function query(
        ?int $id = null,
        ?string $user_name = null,
        ?string $keyword = null,
        int $limit = 20,
    ): array {
        $limit = min(max($limit, 1), self::MAX_LIMIT);

        $q = McpTenant::table('sys_admin');

        if ($id !== null) {
            $q->where('id', $id);
        }
        if ($user_name !== null && $user_name !== '') {
            $q->where('user_name', $user_name);
        }
        if ($keyword !== null && $keyword !== '') {
            $q->where(function ($sub) use ($keyword) {
                $kw = "%{$keyword}%";
                $sub->where('user_name', 'like', $kw)
                    ->orWhere('real_name', 'like', $kw)
                    ->orWhere('nick_name', 'like', $kw);
            });
        }

        $rows = $q->orderByDesc('id')->limit($limit)->get(self::SAFE_FIELDS)->toArray();

        return [
            'total'  => count($rows),
            'rows'   => array_map(static fn ($r) => (array) $r, $rows),
            'fields' => self::SAFE_FIELDS,
        ];
    }
}
