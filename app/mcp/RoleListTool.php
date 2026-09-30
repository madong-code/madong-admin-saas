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

use app\service\admin\system\role\RoleService;
use core\communication\mcp\attribute\McpTool;
use core\communication\mcp\security\McpUser;
use support\Container;

/**
 * role_list：角色查询（L2 app 桥接示范：直接依赖 app\service，按权限码过滤）
 */
final class RoleListTool
{
    public function __construct(
        private readonly ?McpUser $user = null,
    ) {
    }

    #[McpTool(
        name: 'role_list',
        title: 'Role List',
        description: '查询 madong 后台角色清单（编码/名称/类型/启用状态），可按名称或编码关键词过滤。',
        inputSchema: [
            'type' => 'object',
            'properties' => [
                'keyword' => [
                    'type' => 'string',
                    'description' => '按角色名称/编码过滤（空=全部）',
                ],
            ],
        ],
        permission: 'system:role:list',
    )]
    public function roleList(string $keyword = ''): array
    {
        $service = Container::get(RoleService::class);
        $roles   = $service->getAllRoles();
        $keyword = trim($keyword);

        $result = [];
        foreach ($roles as $role) {
            $row = is_object($role) && method_exists($role, 'toArray') ? $role->toArray() : (array) $role;
            $name = (string) ($row['name'] ?? '');
            $code = (string) ($row['code'] ?? '');
            if ($keyword !== '' && stripos($name, $keyword) === false && stripos($code, $keyword) === false) {
                continue;
            }
            $result[] = [
                'id'        => (string) ($row['id'] ?? ''),
                'pid'       => (string) ($row['pid'] ?? '0'),
                'name'      => $name,
                'code'      => $code,
                'role_type' => $row['role_type'] ?? null,
                'enabled'   => (bool) ($row['enabled'] ?? false),
                'sort'      => (int) ($row['sort'] ?? 0),
            ];
        }

        return ['total' => count($result), 'roles' => $result];
    }
}
