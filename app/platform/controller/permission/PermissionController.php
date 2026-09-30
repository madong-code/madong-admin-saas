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
namespace app\platform\controller\permission;

use app\platform\controller\Base;
use app\platform\validate\permission\PermissionValidate;
use app\service\admin\system\MenuService;
use core\business\service\FieldPermissionService;
use core\foundation\tool\Json;
use madong\swagger\annotation\response\PageResponse;
use madong\swagger\annotation\response\SimpleResponse;
use OpenApi\Attributes as OA;
use support\annotation\Middleware;
use support\Container;
use support\Request;

#[Middleware(\app\platform\middleware\AccessTokenMiddleware::class)]
final class PermissionController extends Base
{
    protected FieldPermissionService $fieldPermissionService;
    protected MenuService $menuService;

    public function __construct(FieldPermissionService $fieldPermissionService)
    {
        $this->fieldPermissionService = $fieldPermissionService;
        $this->menuService = Container::make(MenuService::class);
        $this->validate = new PermissionValidate();
    }

    #[OA\Get(
        path: '/permissions',
        summary: '获取权限列表',
        tags: ['权限管理'],
        parameters: [
            new OA\Parameter(name: 'module', description: '模块名称', in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'tenant_id', description: '租户ID', in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'page', description: '页码', in: 'query', schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'limit', description: '每页数量', in: 'query', schema: new OA\Schema(type: 'integer', default: 20)),
        ]
    )]
    #[PageResponse]
    public function index(Request $request): \support\Response
    {
        try {
            $module = $request->input('module');
            $tenantId = $request->input('tenant_id');
            
            $permissions = $this->fieldPermissionService->getAllowedFields($module, $tenantId);
            
            return Json::success('ok', [
                'tenant_id' => $tenantId,
                'module' => $module,
                'allowed_fields' => $permissions,
            ]);
        } catch (\Throwable $e) {
            return Json::fail($e->getMessage());
        }
    }

    #[OA\Get(
        path: '/permissions/{id}',
        summary: '获取权限详情',
        tags: ['权限管理'],
        parameters: [
            new OA\Parameter(name: 'id', description: '权限ID', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ]
    )]
    #[SimpleResponse]
    public function show(Request $request): \support\Response
    {
        try {
            $id = $request->route->param('id');
            
            if (empty($id)) {
                return Json::fail('权限ID不能为空', [], 400);
            }
            
            $permission = $this->menuService->get($id);
            if (empty($permission)) {
                return Json::fail('权限不存在', [], 404);
            }
            
            return Json::success('ok', $permission->toArray());
        } catch (\Throwable $e) {
            return Json::fail($e->getMessage(), [], $e->getCode() ?: 400);
        }
    }

    #[OA\Post(
        path: '/permissions',
        summary: '创建权限',
        tags: ['权限管理'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name', 'code', 'type'],
                properties: [
                    new OA\Property(property: 'name', description: '权限名称', type: 'string'),
                    new OA\Property(property: 'code', description: '权限编码', type: 'string'),
                    new OA\Property(property: 'parent_id', description: '父级ID', type: 'string'),
                    new OA\Property(property: 'type', description: '类型(1:菜单,2:目录,3:按钮,4:API)', type: 'integer', enum: [1, 2, 3, 4]),
                    new OA\Property(property: 'path', description: '路径', type: 'string'),
                    new OA\Property(property: 'icon', description: '图标', type: 'string'),
                    new OA\Property(property: 'sort', description: '排序', type: 'integer'),
                    new OA\Property(property: 'enabled', description: '是否启用', type: 'integer', enum: [0, 1]),
                    new OA\Property(property: 'permission', description: '权限标识', type: 'string'),
                ]
            )
        )
    )]
    #[SimpleResponse(schema: [], example: ['id' => 'xxx'])]
    public function store(Request $request): \support\Response
    {
        try {
            $data = $this->insertInput($request);
            $this->validate->scene('create')->check($data);
            
            $model = $this->menuService->save($data);
            if (empty($model)) {
                return Json::fail('创建权限失败', [], 500);
            }
            
            return Json::success('创建成功', ['id' => $model->getPk()]);
        } catch (\Throwable $e) {
            return Json::fail($e->getMessage());
        }
    }

    #[OA\Put(
        path: '/permissions/{id}',
        summary: '更新权限',
        tags: ['权限管理'],
        parameters: [
            new OA\Parameter(name: 'id', description: '权限ID', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'name', description: '权限名称', type: 'string'),
                    new OA\Property(property: 'code', description: '权限编码', type: 'string'),
                    new OA\Property(property: 'parent_id', description: '父级ID', type: 'string'),
                    new OA\Property(property: 'type', description: '类型(1:菜单,2:目录,3:按钮,4:API)', type: 'integer', enum: [1, 2, 3, 4]),
                    new OA\Property(property: 'path', description: '路径', type: 'string'),
                    new OA\Property(property: 'icon', description: '图标', type: 'string'),
                    new OA\Property(property: 'sort', description: '排序', type: 'integer'),
                    new OA\Property(property: 'enabled', description: '是否启用', type: 'integer', enum: [0, 1]),
                    new OA\Property(property: 'permission', description: '权限标识', type: 'string'),
                ]
            )
        )
    )]
    #[SimpleResponse]
    public function update(Request $request): \support\Response
    {
        try {
            $id = $request->route->param('id');
            
            if (empty($id)) {
                return Json::fail('权限ID不能为空', [], 400);
            }
            
            $data = $this->insertInput($request);
            $data['id'] = $id;
            $this->validate->scene('update')->check($data);
            
            $this->menuService->update($id, $data);
            return Json::success('更新成功');
        } catch (\Throwable $e) {
            return Json::fail($e->getMessage());
        }
    }

    #[OA\Delete(
        path: '/permissions/{id}',
        summary: '删除权限',
        tags: ['权限管理'],
        parameters: [
            new OA\Parameter(name: 'id', description: '权限ID', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ]
    )]
    #[SimpleResponse]
    public function destroy(Request $request): \support\Response
    {
        try {
            $id = $request->route->param('id');
            
            if (empty($id)) {
                return Json::fail('权限ID不能为空', [], 400);
            }
            
            $this->menuService->delete($id);
            return Json::success('删除成功');
        } catch (\Throwable $e) {
            return Json::fail($e->getMessage());
        }
    }

    #[OA\Put(
        path: '/permissions/{id}/status',
        summary: '切换权限状态',
        tags: ['权限管理'],
        parameters: [
            new OA\Parameter(name: 'id', description: '权限ID', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['enabled'],
                properties: [
                    new OA\Property(property: 'enabled', description: '是否启用', type: 'integer', enum: [0, 1]),
                ]
            )
        )
    )]
    #[SimpleResponse]
    public function changeStatus(Request $request): \support\Response
    {
        try {
            $id = $request->route->param('id');
            $enabled = (int)$request->post('enabled', 0);
            
            if (empty($id)) {
                return Json::fail('权限ID不能为空', [], 400);
            }
            
            $this->menuService->update($id, ['enabled' => $enabled]);
            return Json::success('状态更新成功');
        } catch (\Throwable $e) {
            return Json::fail($e->getMessage());
        }
    }

    #[OA\Get(
        path: '/permissions/modules/{module}/detail',
        summary: '获取模块权限详情',
        tags: ['权限管理'],
        parameters: [
            new OA\Parameter(name: 'module', description: '模块名称', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'tenant_id', description: '租户ID', in: 'query', schema: new OA\Schema(type: 'string')),
        ]
    )]
    #[SimpleResponse]
    public function detail(Request $request): \support\Response
    {
        try {
            $module = $request->route->param('module');
            $tenantId = $request->input('tenant_id');
            
            if (empty($module)) {
                return Json::fail('模块名称不能为空', [], 400);
            }
            
            return Json::success('ok', $this->fieldPermissionService->getModulePermissionInfo($module, $tenantId));
        } catch (\Throwable $e) {
            return Json::fail($e->getMessage());
        }
    }

    #[OA\Get(
        path: '/permission-stats',
        summary: '获取权限统计',
        tags: ['权限管理']
    )]
    #[SimpleResponse]
    public function stats(Request $request): \support\Response
    {
        try {
            return Json::success('ok', $this->fieldPermissionService->getStats());
        } catch (\Throwable $e) {
            return Json::fail($e->getMessage());
        }
    }

    #[OA\Get(
        path: '/permission-tree',
        summary: '获取权限树结构',
        tags: ['权限管理'],
        parameters: [
            new OA\Parameter(name: 'enabled', description: '是否只返回启用的', in: 'query', schema: new OA\Schema(type: 'integer', enum: [0, 1])),
        ]
    )]
    #[SimpleResponse]
    public function tree(Request $request): \support\Response
    {
        try {
            $enabled = $request->input('enabled');
            $where = [];
            
            if ($enabled !== null) {
                $where['enabled'] = (int)$enabled;
            }
            
            $menus = $this->menuService->selectList($where, '*', 0, 0, 'sort', [], true);
            return Json::success('ok', $this->buildTree($menus->toArray()));
        } catch (\Throwable $e) {
            return Json::fail($e->getMessage());
        }
    }

    /**
     * 构建树形结构
     */
    protected function buildTree(array $items, ?string $parentId = null): array
    {
        $tree = [];
        
        foreach ($items as $item) {
            if ($item['parent_id'] == $parentId) {
                $children = $this->buildTree($items, $item['id']);
                if (!empty($children)) {
                    $item['children'] = $children;
                }
                $tree[] = $item;
            }
        }
        
        return $tree;
    }
}
