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
namespace app\platform\controller\system;

use app\platform\controller\Base;
use app\service\admin\system\MenuService;
use core\foundation\tool\Json;
use madong\swagger\annotation\response\SimpleResponse;
use OpenApi\Attributes as OA;
use support\Container;
use support\Request;
use support\annotation\Middleware;
use Webman\Http\Response;

/**
 * 套餐权限菜单（关联 sys_menu）
 */
#[OA\Tag(name: '套餐权限管理')]
#[Middleware(\app\platform\middleware\AccessTokenMiddleware::class)]
final class PermissionController extends Base
{
    /**
     * 获取权限菜单树（用于套餐授权权限选择）
     */
    #[OA\Get(
        path: '/subscription/permission/tree',
        summary: '权限菜单树',
        tags: ['套餐权限管理'],
    )]
    #[SimpleResponse(schema: [], example: [])]
    public function tree(Request $request): Response
    {
        try {
            /** @var MenuService $menuService */
            $menuService = Container::make(MenuService::class);
            $allMenus    = $menuService->getPermissionTree();
            $tree        = $this->formatPermissionTree($allMenus);
            return Json::success('ok', $tree);
        } catch (\Throwable $e) {
            return Json::fail($e->getMessage());
        }
    }

    /**
     * 将 sys_menu 树格式化为前端权限树格式
     */
    public function formatPermissionTree(array $menus): array
    {
        $result = [];
        foreach ($menus as $menu) {
            $node = [
                'key'      => (string)$menu['id'],
                'label'    => $menu['title'] ?? '',
                'type'     => (int)($menu['type'] ?? 0),
                'children' => [],
            ];

            // type 4（接口）需要 method 和 path
            if ((int)($menu['type'] ?? 0) === 4) {
                $methods = $menu['methods'] ?? '';
                // 如果有多个 methods，取第一个
                $node['method'] = explode(',', $methods)[0] ?? '';
                $node['path']   = $menu['code'] ?? $menu['path'] ?? '';
            }

            // 递归处理子节点
            if (!empty($menu['children'])) {
                $node['children'] = $this->formatPermissionTree($menu['children']);
            }

            $result[] = $node;
        }
        return $result;
    }
}
