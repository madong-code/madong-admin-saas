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

namespace app\adminapi\controller\web;

use app\adminapi\controller\Crud;
use app\adminapi\middleware\AccessTokenMiddleware;
use app\adminapi\middleware\OperationMiddleware;
use app\adminapi\middleware\PermissionMiddleware;
use app\adminapi\validate\web\MenuValidate;
use app\schema\request\IdRequest;
use app\service\admin\web\MenuService;
use madong\swagger\attribute\Permission;
use core\foundation\tool\Json;
use madong\swagger\annotation\response\SimpleResponse;
use OpenApi\Attributes as OA;
use support\annotation\Middleware;
use support\Request;
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
        path: '/web/menu',
        summary: '菜单列表',
        tags: ['菜单管理'],
        parameters: [
            new OA\Parameter(name: "name", description: "菜单名称", in: "query", schema: new OA\Schema(type: "string")),
            new OA\Parameter(name: "type", description: "菜单类型", in: "query", schema: new OA\Schema(type: "string")),
            new OA\Parameter(name: "page", description: "页码", in: "query", schema: new OA\Schema(type: "integer")),
            new OA\Parameter(name: "limit", description: "每页数量", in: "query", schema: new OA\Schema(type: "integer")),
        ]
    )]
    #[Permission("web:menu:list")]
    #[SimpleResponse(schema: [], example: [])]
    public function index(Request $request): \support\Response
    {
        try {
            [$where, $format, $limit, $field, $order, $page] = $this->selectInput($request);
            $methods = [
                'select'     => 'formatSelect',
                'tree'       => 'formatTree',
                'table_tree' => 'formatTableTree',
                'normal'     => 'formatNormal',
            ];
            if (empty($order)) {
                $order = 'sort asc';
            }
            $format_function = $methods[$format] ?? 'formatNormal';
            $total           = $this->service->getCount($where);
            $list            = $this->service->selectList($where, $field, $page, $limit, $order, [], false);
            return call_user_func([$this, $format_function], $list, $total);
        } catch (\Throwable $e) {
            return Json::fail($e->getMessage());
        }
    }

    #[OA\Get(
        path: '/web/menu/{id}',
        summary: '菜单详情',
        tags: ['菜单管理'],
        parameters: [
            new OA\Parameter(name: "id", description: "菜单ID", in: "path", required: true, schema: new OA\Schema(type: "integer")),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "成功",
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: "code", type: "integer", example: 0),
                    new OA\Property(property: "msg", type: "string", example: "获取成功"),
                    new OA\Property(property: "data", properties: [
                        new OA\Property(property: "id", type: "integer", example: 1),
                        new OA\Property(property: "name", type: "string", example: "首页"),
                        new OA\Property(property: "type", type: "string", example: "page"),
                        new OA\Property(property: "path", type: "string", example: "/home"),
                        new OA\Property(property: "title", type: "string", example: "首页"),
                        new OA\Property(property: "url", type: "string", example: ""),
                        new OA\Property(property: "icon", type: "string", example: "home"),
                        new OA\Property(property: "sort", type: "integer", example: 1),
                    ], type: "object"),
                ])
            ),
        ],
        x: [
            SchemaConstants::X_PROPERTY_IN    => 'id',
            SchemaConstants::X_SCHEMA_REQUEST => IdRequest::class,
        ]
    )]
    #[Permission("web:menu:read")]
    #[SimpleResponse(schema: [], example: [])]
    public function show(Request $request): \support\Response
    {
        return parent::show($request);
    }

    #[OA\Post(
        path: '/web/menu',
        summary: '创建',
        tags: ['菜单管理'],
    )]
    #[Permission("web:menu:create")]
    #[SimpleResponse(schema: [], example: [])]
    public function store(Request $request): \support\Response
    {
        return parent::store($request);
    }

    #[OA\Put(
        path: '/web/menu/{id}',
        summary: '更新菜单',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(properties: [
                new OA\Property(property: "name", type: "string", example: "首页"),
                new OA\Property(property: "type", type: "string", example: "page"),
                new OA\Property(property: "path", type: "string", example: "/home"),
                new OA\Property(property: "title", type: "string", example: "首页"),
                new OA\Property(property: "url", type: "string", example: ""),
                new OA\Property(property: "icon", type: "string", example: "home"),
                new OA\Property(property: "sort", type: "integer", example: 1),
            ])
        ),
        tags: ['菜单管理'],
        parameters: [
            new OA\Parameter(name: "id", description: "菜单ID", in: "path", required: true, schema: new OA\Schema(type: "integer")),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "成功",
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: "code", type: "integer", example: 0),
                    new OA\Property(property: "msg", type: "string", example: "更新成功"),
                    new OA\Property(property: "data", properties: [
                        new OA\Property(property: "id", type: "integer", example: 1),
                    ], type: "object"),
                ])
            ),
        ]
    )]
    #[Permission("web:menu:update")]
    #[SimpleResponse(schema: [], example: [])]
    public function update(Request $request): \support\Response
    {
        return parent::update($request);
    }

    #[OA\Delete(
        path: '/web/menu',
        summary: '删除菜单',
        tags: ['菜单管理'],
        parameters: [
            new OA\Parameter(name: "id", description: "菜单ID", in: "path", required: true, schema: new OA\Schema(type: "integer")),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "成功",
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: "code", type: "integer", example: 0),
                    new OA\Property(property: "msg", type: "string", example: "删除成功"),
                    new OA\Property(property: "data", properties: [
                        new OA\Property(property: "ids", type: "array", items: new OA\Items(type: "integer")),
                    ]),
                ])
            ),
        ],
        x: [
            SchemaConstants::X_PROPERTY_IN    => 'id',
            SchemaConstants::X_SCHEMA_REQUEST => IdRequest::class,
        ]
    )]
    #[Permission("web:menu:delete")]
    #[SimpleResponse(schema: [], example: [])]
    public function destroy(Request $request): \support\Response
    {
        try {
            $ids = $request->input('ids',[]);
            $this->service->batchDelete($ids);
            return Json::success('删除成功');
        } catch (\Throwable $e) {
            return Json::fail($e->getMessage());
        }
    }
}

