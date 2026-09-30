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
use app\adminapi\schema\request\system\MenuFormRequest;
use app\adminapi\schema\request\system\MenuQueryRequest;
use app\adminapi\schema\response\system\MenuResponse;
use app\adminapi\validate\system\menu\MenuValidate;
use app\schema\request\BatchDeleteRequest;
use app\scope\global\AccessPermissionScope;
use app\service\admin\system\menu\MenuService;
use app\service\core\plugin\PluginService;
use core\foundation\exception\handler\AdminException;
use core\foundation\tool\Json;
use madong\helper\Arr;
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
final class MenuController extends Crud
{

    public function __construct(MenuService $service, MenuValidate $validate)
    {
        $this->service  = $service;
        $this->validate = $validate;
    }

    #[OA\Get(
        path: '/system/menu',
        summary: '列表',
        tags: ['菜单管理'],
        x: [
            SchemaConstants::X_SCHEMA_REQUEST => MenuQueryRequest::class,
        ]
    )]
    #[Permission(code: 'system:menu:list')]
    #[PageResponse(schema: MenuResponse::class, example: [])]
    public function index(Request $request): \support\Response
    {
        return parent::index($request);
    }

    #[OA\Get(
        path: '/system/menu/{id}',
        summary: '详情',
        tags: ['菜单管理'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: '菜单ID',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string', example: 1)
            ),
        ],
    )]
    #[Permission(code: 'system:menu:read')]
    #[SimpleResponse(schema: MenuResponse::class, example: [])]
    public function show(Request $request): \support\Response
    {
        return parent::show($request);
    }

    #[OA\Post(
        path: '/system/menu',
        summary: '创建',
        tags: ['菜单管理'],
        x: [
            SchemaConstants::X_SCHEMA_REQUEST => MenuFormRequest::class,
        ]
    )]
    #[Permission(code: 'system:menu:create')]
    #[SimpleResponse(schema: [], example: [])]
    public function store(Request $request): \support\Response
    {
        return parent::store($request);
    }

    #[OA\Put(
        path: '/system/menu/{id}',
        summary: '更新',
        tags: ['菜单管理'],
        x: [
            SchemaConstants::X_SCHEMA_REQUEST => MenuFormRequest::class,
        ]
    )]
    #[OA\Parameter(
        name: 'id',
        description: '菜单ID',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'string', example: 1)
    )]
    #[Permission(code: 'system:menu:update')]
    #[SimpleResponse(schema: [], example: [])]
    public function update(Request $request): \support\Response
    {
        try {
            $id    = $request->route->param('id');
            $data  = $request->all();
            $model = $this->service->update($id, $data);
            return Json::success('更新成功', $model->toArray());
        } catch (\Throwable $e) {
            return Json::fail($e->getMessage());
        }
    }

    #[OA\Delete(
        path: '/system/menu',
        summary: '删除',
        tags: ['菜单管理'],
        x: [
            SchemaConstants::X_SCHEMA_REQUEST => BatchDeleteRequest::class,
        ]
    )]
    #[Permission(code: 'system:menu:delete')]
    #[SimpleResponse(schema: [], example: [])]
    public function destroy(Request $request): \support\Response
    {
        try {
            $data = $this->getDeleteIds($request);
            if (empty($data)) {
                throw new AdminException('参数错误');
            }
            $result = $this->service->batchDelete($data);
            return Json::success('ok', $result);
        } catch (\Throwable $e) {
            return Json::fail($e->getMessage());
        }
    }

    #[OA\Delete(
        path: '/system/menu/{id}', 
        summary: '删除菜单模板',
         tags: ['菜单模板']
    )]
    #[SimpleResponse(schema: [], example: [])]
    public function delete(Request $request): \support\Response
    {
        try {
            $id = $request->route->param('id');
            $this->service->batchDelete(Arr::normalize($id));
            return Json::success('删除成功');
        } catch (\Throwable $e) {
            return Json::fail($e->getMessage());
        }
    }

    #[OA\Post(
        path: '/system/menu/batch-store',
        summary: '批量添加菜单',
        tags: ['菜单管理'],
    )]
    #[Permission(code: 'system:menu:batch_store')]
    #[RequestBody(required: true, content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: MenuFormRequest::class)))]
    #[SimpleResponse(schema: [], example: [])]
    public function batchStore(Request $request): \support\Response
    {
        try {
            $params = $request->input('menus', []);
            $data   = [];
            if (isset($this->validate) && $this->validate) {
                foreach ($params as $param) {
                    $data[] = $this->inputFilter($param);
                    if (!$this->validate->scene('batch-store')->check($param)) {
                        throw new \Exception($this->validate->getError());
                    }
                }
            }
            foreach ($data as $item) {
                $this->service->save($item);
            }
            return Json::success('ok');
        } catch (\Exception $e) {
            return Json::fail($e->getMessage());
        }
    }

    #[OA\Get(
        path: '/system/menu/app/list',
        summary: '获取应用列表',
        tags: ['菜单管理'],
    )]
    #[Permission(code: 'system:menu:app_list')]
    #[SimpleResponse(schema: [], example: [])]
    public function appList(Request $request): \support\Response
    {
        try {
            /**@var PluginService $service */
            $service = Container::make(PluginService::class);
            $model   = $service->selectList([], '*', 0, 0, '', [], false, [AccessPermissionScope::class]);
            return $this->formatAppSelect($model);
        } catch (\Exception $e) {
            return Json::fail('获取应用列表失败', []);
        }
    }

    private function formatAppSelect($items): \support\Response
    {
        $formatted_items   = [];
        $formatted_items[] = ['label' => '系统应用', 'value' => 'admin'];
        foreach ($items as $item) {
            $formatted_items[] = [
                'label' => $item->title ?? $item->name ?? $item->real_name ?? $item->id,
                'value' => $item->id,
            ];
        }
        return Json::success('ok', $formatted_items);
    }
}
