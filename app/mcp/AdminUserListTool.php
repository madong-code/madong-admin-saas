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

namespace app\mcp;

use app\service\admin\system\admin\AdminService;
use core\communication\mcp\attribute\McpTool;
use core\communication\mcp\security\McpUser;
use support\Container;

/**
 * admin_user_list：管理员查询（L2 app 桥接示范：直接依赖 app\service，按权限码过滤）
 */
final class AdminUserListTool
{
    public function __construct(
        private readonly ?McpUser $user = null,
    ) {
    }

    #[McpTool(
        name: 'admin_user_list',
        title: 'Admin User List',
        description: '查询 madong 后台管理员：keyword 为空返回分页列表，给定则按用户名精确查找单个管理员（不含密码等敏感字段）。',
        inputSchema: [
            'type' => 'object',
            'properties' => [
                'keyword' => [
                    'type' => 'string',
                    'description' => '管理员用户名（精确匹配，空=分页列表）',
                ],
                'page' => [
                    'type' => 'integer',
                    'default' => 1,
                    'minimum' => 1,
                    'description' => '页码',
                ],
                'limit' => [
                    'type' => 'integer',
                    'default' => 20,
                    'maximum' => 100,
                    'description' => '每页条数',
                ],
            ],
        ],
        permission: 'system:admin:list',
    )]
    public function adminUserList(string $keyword = '', int $page = 1, int $limit = 20): array
    {
        $service = Container::get(AdminService::class);
        $page    = max(1, $page);
        $limit   = min(100, max(1, $limit));

        $keyword = trim($keyword);
        if ($keyword !== '') {
            $admin = $service->getAdminByName($keyword, null);
            return [
                'total' => $admin === null ? 0 : 1,
                'users' => $admin === null ? [] : [$this->format($admin)],
            ];
        }

        [$total, $items] = $service->getList([], '*', $page, $limit);
        return [
            'total' => $total,
            'page'  => $page,
            'limit' => $limit,
            'users' => array_map($this->format(...), $items->all()),
        ];
    }

    private function format(object $admin): array
    {
        return [
            'id'             => (string) $admin->id,
            'user_name'      => (string) ($admin->user_name ?? ''),
            'real_name'      => (string) ($admin->real_name ?? ''),
            'is_super'       => (bool) ($admin->is_super ?? false),
            'main_dept_name' => (string) ($admin->main_dept_name ?? ''),
            'main_post_name' => (string) ($admin->main_post_name ?? ''),
        ];
    }
}
