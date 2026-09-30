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

namespace app\adminapi\controller\system;

use app\adminapi\controller\Crud;
use app\adminapi\middleware\AccessTokenMiddleware;
use app\adminapi\middleware\OperationMiddleware;
use app\adminapi\middleware\PermissionMiddleware;
use app\adminapi\schema\request\system\RoleFormRequest;
use app\adminapi\schema\request\system\RoleQueryRequest;
use app\adminapi\schema\response\system\RoleResponse;
use app\adminapi\validate\system\role\RoleValidate;
use app\schema\request\BatchDeleteRequest;
use app\schema\request\IdRequest;
use app\scope\global\AccessPermissionScope;
use app\service\admin\system\org\DeptService;
use app\service\admin\system\role\RoleService;
use core\foundation\exception\handler\AdminException;
use core\foundation\tool\Json;
use madong\swagger\annotation\response\PageResponse;
use madong\swagger\annotation\response\SimpleResponse;
use madong\swagger\attribute\Permission;
use OpenApi\Attributes as OA;
use OpenApi\Attributes\RequestBody;
use support\Container;
use support\Request;
use support\annotation\Middleware;
use WebmanTech\Swagger\DTO\SchemaConstants;

#[Middleware(AccessTokenMiddleware::class, PermissionMiddleware::class, OperationMiddleware::class)]
final class RoleController extends Crud
{
    public function __construct(RoleService $service, RoleValidate $validate)
    {
        $this->service  = $service;
        $this->validate = $validate;
    }

    #[OA\Get(
        path: '/system/role',
        summary: '列表',
        tags: ['角色管理'],
        x: [
            SchemaConstants::X_SCHEMA_REQUEST => RoleQueryRequest::class,
        ]
    )]
    #[Permission(code: ['system:role:list'])]
    #[PageResponse(schema: RoleResponse::class, example: [])]
    public function index(Request $request): \support\Response
    {
        return parent::index($request);
    }

    #[OA\Get(
        path: '/system/role/{id}',
        summary: '详情',
        tags: ['角色管理'],
        x: [
            SchemaConstants::X_PROPERTY_IN => 'id',
            SchemaConstants::X_SCHEMA_REQUEST => IdRequest::class,
        ]
    )]
    #[Permission(code: 'system:role:read')]
    #[SimpleResponse(schema: RoleResponse::class, example: [])]
    public function show(Request $request): \support\Response
    {
        try {
            $id   = $request->route->param('id');
            $data = $this->service->get($id, ['*'], ['scopes']);
            if (empty($data)) {
                throw new AdminException('数据未找到', 400);
            }
            return Json::success('ok', $data->toArray());
        } catch (\Throwable $e) {
            return Json::fail($e->getMessage(), [], $e->getCode());
        }
    }

    #[OA\Post(
        path: '/system/role',
        summary: '新增',
        tags: ['角色管理'],
        x: [
            SchemaConstants::X_SCHEMA_REQUEST => RoleFormRequest::class,
        ]
    )]
    #[Permission(code: 'system:role:create')]
    #[SimpleResponse(schema: [], example: [])]
    public function store(Request $request): \support\Response
    {
        try {
            $data = $this->inputFilter($request->all(), ['permissions']);
            if (isset($this->validate) && $this->validate) {
                if (!$this->validate->scene('store')->check($data)) {
                    throw new \Exception($this->validate->getError());
                }
            }
            $model = $this->service->save($data);
            if (empty($model)) {
                throw new AdminException('插入失败');
            }
            $pk = $model->getPk();
            return Json::success('ok', [$pk => $model->getData($pk)]);
        } catch (\Throwable $e) {
            return Json::fail($e->getMessage());
        }
    }

    #[OA\Put(
        path: '/system/role/{id}',
        summary: '更新',
        tags: ['角色管理'],
        x: [
            SchemaConstants::X_SCHEMA_REQUEST => RoleFormRequest::class,
        ]
    )]
    #[OA\Parameter(
        name: 'id',
        description: 'ID',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'string', example: 1)
    )]
    #[Permission(code: 'system:role:update')]
    #[SimpleResponse(schema: [], example: [])]
    public function update(Request $request): \support\Response
    {
        try {
            $id   = $request->route->param('id');
            $data = $this->inputFilter($request->all(), ['permissions', 'scopes']);
            $data['id'] = $id;
            if (isset($this->validate) && $this->validate) {
                if (!$this->validate->scene('update')->check($data)) {
                    throw new \Exception($this->validate->getError());
                }
            }
            $this->service->update($id, $data);
            return Json::success('ok', []);
        } catch (\Throwable $e) {
            return Json::fail($e->getMessage());
        }
    }

    #[OA\Delete(
        path: '/system/role/{id}',
        summary: '删除',
        tags: ['角色管理'],
        x: [
            SchemaConstants::X_PROPERTY_IN => 'id',
            SchemaConstants::X_SCHEMA_REQUEST => IdRequest::class,
        ]
    )]
    #[Permission(code: 'system:role:delete')]
    #[SimpleResponse(schema: [], example: [])]
    public function destroy(Request $request): \support\Response
    {
        return parent::destroy($request);
    }

    #[OA\Delete(
        path: '/system/role',
        summary: '批量删除',
        tags: ['角色管理'],
        x: [
            SchemaConstants::X_SCHEMA_REQUEST => BatchDeleteRequest::class,
        ]
    )]
    #[Permission(code: 'system:role:delete')]
    #[SimpleResponse(schema: [], example: [])]
    public function batchDelete(Request $request): \support\Response
    {
        return parent::destroy($request);
    }

    #[OA\Put(
        path: '/system/role/{id}/data-scope',
        summary: '分配数据权限',
        tags: ['角色管理'],
        x: [
            SchemaConstants::X_PROPERTY_IN => 'id',
            SchemaConstants::X_SCHEMA_REQUEST => IdRequest::class,
        ]
    )]
    #[RequestBody(required: true, content: new OA\JsonContent(
        properties: [
            new OA\Property(property: 'id', type: 'integer', example: 1),
            new OA\Property(property: 'data_scope', type: 'integer', example: 1),
            new OA\Property(property: 'scopes', type: 'array', items: new OA\Items(type: 'string')),
        ]
    ))]
    #[Permission(code: 'system:role:data_scope')]
    #[SimpleResponse(schema: [], example: [])]
    public function dataScope(Request $request): \support\Response
    {
        try {
            $id   = $request->route->param('id');
            $data = $this->inputFilter($request->all(), ['scopes']);
            $data['id'] = $id;
            if (isset($this->validate) && $this->validate) {
                if (!$this->validate->scene('data-scope')->check($data)) {
                    throw new \Exception($this->validate->getError());
                }
            }
            $this->service->updateScope($id, $data);
            return Json::success('ok', []);
        } catch (\Throwable $e) {
            return Json::fail($e->getMessage());
        }
    }

    #[OA\Put(
        path: '/system/role/{id}/change-status',
        summary: '切换状态',
        tags: ['角色管理'],
        x: [
            SchemaConstants::X_PROPERTY_IN => 'id',
            SchemaConstants::X_SCHEMA_REQUEST => IdRequest::class,
        ]
    )]
    #[RequestBody(required: true, content: new OA\JsonContent(
        properties: [
            new OA\Property(property: 'status', type: 'string', example: 1),
        ]
    ))]
    #[Permission(code: 'system:role:status')]
    #[SimpleResponse(schema: [], example: [])]
    public function changeStatus(Request $request): \support\Response
    {
        return parent::changeStatus($request);
    }

    #[OA\Get(
        path: '/system/role/scope/dept',
        summary: '获取部门权限树',
        tags: ['角色管理']
    )]
    public function getDeptScope(Request $request): \support\Response
    {
        try {
            /** @var DeptService $service */
            $service = Container::make(DeptService::class);
            $iterm   = $service->selectList([], ['id', 'pid', 'name'], 0, 0, 'created_at', [], false, [AccessPermissionScope::class]);
            return $this->formatTree($iterm);
        } catch (\Throwable $e) {
            return Json::fail($e->getMessage());
        }
    }

}
