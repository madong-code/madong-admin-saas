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

namespace app\platform\controller\web;

use app\adminapi\validate\web\MenuValidate;
use app\platform\controller\Base;
use app\schema\request\IdRequest;
use app\service\admin\web\MenuService;
use core\foundation\tool\Json;
use OpenApi\Attributes as OA;
use support\annotation\Middleware;
use support\Request;
use WebmanTech\Swagger\DTO\SchemaConstants;

#[Middleware(\app\platform\middleware\AccessTokenMiddleware::class)]
final class MenuController extends Base
{
    public function __construct(MenuService $service, MenuValidate $validate)
    {
        $this->service  = $service;
        $this->validate = $validate;
    }

    #[OA\Get(
        path: '/web/menu',
        summary: '菜单列表',
        tags: ['前台菜单'],
        parameters: [
            new OA\Parameter(name: "name", description: "菜单名称", in: "query", schema: new OA\Schema(type: "string")),
            new OA\Parameter(name: "type", description: "菜单类型", in: "query", schema: new OA\Schema(type: "string")),
            new OA\Parameter(name: "page", description: "页码", in: "query", schema: new OA\Schema(type: "integer")),
            new OA\Parameter(name: "limit", description: "每页数量", in: "query", schema: new OA\Schema(type: "integer")),
        ]
    )]
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
        tags: ['前台菜单'],
        parameters: [
            new OA\Parameter(name: "id", description: "菜单ID", in: "path", required: true, schema: new OA\Schema(type: "integer")),
        ],
        x: [
            SchemaConstants::X_PROPERTY_IN    => 'id',
            SchemaConstants::X_SCHEMA_REQUEST => IdRequest::class,
        ]
    )]
    public function show(Request $request): \support\Response
    {
        return parent::show($request);
    }

    #[OA\Post(
        path: '/web/menu',
        summary: '创建菜单',
        tags: ['前台菜单'],
    )]
    public function store(Request $request): \support\Response
    {
        return parent::store($request);
    }

    #[OA\Put(
        path: '/web/menu/{id}',
        summary: '更新菜单',
        tags: ['前台菜单'],
        parameters: [
            new OA\Parameter(name: "id", description: "菜单ID", in: "path", required: true, schema: new OA\Schema(type: "integer")),
        ],
    )]
    public function update(Request $request): \support\Response
    {
        return parent::update($request);
    }

    #[OA\Delete(
        path: '/web/menu',
        summary: '删除菜单',
        tags: ['前台菜单'],
        x: [
            SchemaConstants::X_PROPERTY_IN    => 'id',
            SchemaConstants::X_SCHEMA_REQUEST => IdRequest::class,
        ]
    )]
    public function destroy(Request $request): \support\Response
    {
        try {
            $ids = $request->input('ids', []);
            $this->service->batchDelete($ids);
            return Json::success('删除成功');
        } catch (\Throwable $e) {
            return Json::fail($e->getMessage());
        }
    }
}
