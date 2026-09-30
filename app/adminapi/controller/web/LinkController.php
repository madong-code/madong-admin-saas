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
use app\adminapi\validate\web\LinkValidate;
use app\schema\request\IdRequest;
use app\service\admin\web\LinkService;
use madong\swagger\attribute\Permission;
use core\foundation\tool\Json;
use madong\swagger\annotation\response\SimpleResponse;
use OpenApi\Attributes as OA;
use support\annotation\Middleware;
use support\Request;
use WebmanTech\Swagger\DTO\SchemaConstants;

#[Middleware(AccessTokenMiddleware::class, PermissionMiddleware::class, OperationMiddleware::class)]
final class LinkController extends Crud
{
    public function __construct(LinkService $service, LinkValidate $validate)
    {
        $this->service  = $service;
        $this->validate = $validate;
    }

    #[OA\Get(
        path: '/web/link',
        summary: '友情链接列表',
        tags: ['友情链接管理'],
        parameters: [
            new OA\Parameter(name: "name", description: "链接名称", in: "query", schema: new OA\Schema(type: "string")),
            new OA\Parameter(name: "status", description: "状态", in: "query", schema: new OA\Schema(type: "integer")),
            new OA\Parameter(name: "page", description: "页码", in: "query", schema: new OA\Schema(type: "integer")),
            new OA\Parameter(name: "limit", description: "每页数量", in: "query", schema: new OA\Schema(type: "integer")),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "成功",
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: "code", type: "integer", example: 0),
                    new OA\Property(property: "msg", type: "string", example: "获取成功"),
                    new OA\Property(property: "data", properties: [
                        new OA\Property(property: "total", type: "integer", example: 10),
                        new OA\Property(property: "items", type: "array", items: new OA\Items(properties: [
                            new OA\Property(property: "id", type: "integer", example: 1),
                            new OA\Property(property: "name", type: "string", example: "百度"),
                            new OA\Property(property: "url", type: "string", example: "https://www.baidu.com"),
                            new OA\Property(property: "logo", type: "string", example: ""),
                            new OA\Property(property: "logo_type", type: "integer", example: 2, description: "图标类型：1=上传，2=链接"),
                            new OA\Property(property: "sort", type: "integer", example: 1),
                            new OA\Property(property: "enabled", type: "integer", example: 1),
                        ])),
                    ], type: "object"),
                ])
            ),
        ]
    )]
    #[Permission("web:link:list")]
    #[SimpleResponse(schema: [], example: [])]
    public function index(Request $request): \support\Response
    {
        try {
            [$where, $format, $limit, $field, $order, $page] = $this->selectInput($request);
            $methods         = [
                'select'     => 'formatSelect',
                'tree'       => 'formatTree',
                'table_tree' => 'formatTableTree',
                'normal'     => 'formatNormal',
            ];
            $format_function = $methods[$format] ?? 'formatNormal';
            $total           = $this->service->getCount($where);
            $list            = $this->service->selectList($where, $field, $page, $limit, $order, [], false);
            return call_user_func([$this, $format_function], $list, $total);
        } catch (\Throwable $e) {
            return Json::fail($e->getMessage());
        }
    }

    #[OA\Get(
        path: '/web/link/{id}',
        summary: '友情链接详情',
        tags: ['友情链接管理'],
        x: [
            SchemaConstants::X_PROPERTY_IN => 'id',
            SchemaConstants::X_SCHEMA_REQUEST => IdRequest::class,
        ]
    )]
    #[Permission("web:link:read")]
    #[SimpleResponse(schema: [], example: [])]
    public function show(Request $request): \support\Response
    {
        return parent::show($request);
    }

    #[OA\Post(
        path: '/web/link',
        summary: '创建友情链接',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(properties: [
                new OA\Property(property: "name", type: "string", example: "百度"),
                new OA\Property(property: "url", type: "string", example: "https://www.baidu.com"),
                new OA\Property(property: "logo", type: "string", example: ""),
                new OA\Property(property: "logo_type", type: "integer", example: 2, description: "图标类型：1=上传，2=链接"),
                new OA\Property(property: "sort", type: "integer", example: 1),
                new OA\Property(property: "enabled", type: "integer", example: 1),
            ])
        ),
        tags: ['友情链接管理'],
        responses: [
            new OA\Response(
                response: 200,
                description: "成功",
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: "code", type: "integer", example: 0),
                    new OA\Property(property: "msg", type: "string", example: "创建成功"),
                    new OA\Property(property: "data", properties: [
                        new OA\Property(property: "id", type: "integer", example: 1),
                    ], type: "object"),
                ])
            ),
        ],
    )]
    #[Permission("web:link:create")]
    #[SimpleResponse(schema: [], example: [])]
    public function store(Request $request): \support\Response
    {
        return parent::store($request);
    }

    #[OA\Put(
        path: '/web/link/{id}',
        summary: '更新友情链接',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(properties: [
                new OA\Property(property: "name", type: "string", example: "百度"),
                new OA\Property(property: "url", type: "string", example: "https://www.baidu.com"),
                new OA\Property(property: "logo", type: "string", example: ""),
                new OA\Property(property: "logo_type", description: "图标类型：1=上传，2=链接", type: "integer", example: 2),
                new OA\Property(property: "sort", type: "integer", example: 1),
                new OA\Property(property: "enabled", type: "integer", example: 1),
            ])
        ),
        tags: ['友情链接管理'],
        parameters: [
            new OA\Parameter(name: "id", description: "链接ID", in: "path", required: true, schema: new OA\Schema(type: "integer")),
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
    #[Permission("web:link:update")]
    #[SimpleResponse(schema: [], example: [])]
    public function update(Request $request): \support\Response
    {
        return parent::update($request);
    }

    #[OA\Delete(
        path: '/web/link/{id}',
        summary: '删除友情链接',
        tags: ['友情链接管理'],
        parameters: [
            new OA\Parameter(name: "id", description: "链接ID", in: "path", required: true, schema: new OA\Schema(type: "integer")),
        ],
        x: [
            SchemaConstants::X_PROPERTY_IN => 'id',
            SchemaConstants::X_SCHEMA_REQUEST => IdRequest::class,
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "成功",
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: "code", type: "integer", example: 0),
                    new OA\Property(property: "msg", type: "string", example: "删除成功"),
                ])
            ),
        ]
    )]
    #[Permission("web:link:delete")]
    #[SimpleResponse(schema: [], example: [])]
    public function destroy(Request $request): \support\Response
    {
        return parent::destroy($request);
    }
}

