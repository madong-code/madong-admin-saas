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

namespace app\adminapi\controller\member;

use app\adminapi\controller\Crud;
use app\adminapi\middleware\AccessTokenMiddleware;
use app\adminapi\middleware\OperationMiddleware;
use app\adminapi\middleware\PermissionMiddleware;
use app\adminapi\validate\member\MemberPointsValidate;
use app\schema\request\IdRequest;
use app\service\admin\member\MemberPointsService;
use core\foundation\exception\handler\AdminException;
use core\foundation\tool\Json;
use madong\swagger\attribute\Permission;
use OpenApi\Attributes as OA;
use support\Request;
use support\annotation\Middleware;
use WebmanTech\Swagger\DTO\SchemaConstants;

#[Middleware(AccessTokenMiddleware::class, PermissionMiddleware::class, OperationMiddleware::class)]
final class PointsController extends Crud
{

    public function __construct(MemberPointsService $service, MemberPointsValidate $validate)
    {
        $this->service  = $service;
        $this->validate = $validate;
    }

    #[OA\Get(
        path: '/member/points',
        summary: '列表',
        tags: ['会员积分'],
        parameters: [
            new OA\Parameter(name: "member_id", description: "会员ID", in: "query", schema: new OA\Schema(type: "integer")),
            new OA\Parameter(name: "type", description: "类型", in: "query", schema: new OA\Schema(type: "integer")),
            new OA\Parameter(name: "start_time", description: "开始时间", in: "query", schema: new OA\Schema(type: "string")),
            new OA\Parameter(name: "end_time", description: "结束时间", in: "query", schema: new OA\Schema(type: "string")),
            new OA\Parameter(name: "page", description: "页码", in: "query", schema: new OA\Schema(type: "integer")),
            new OA\Parameter(name: "limit", description: "每页数量", in: "query", schema: new OA\Schema(type: "integer")),
        ]
    )]
    #[Permission("member:points:list")]
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
            $list            = $this->service->selectList($where, $field, $page, $limit, $order, ['member'], false);
            return call_user_func([$this, $format_function], $list, $total);
        } catch (\Throwable $e) {
            return Json::fail($e->getMessage());
        }
    }

    #[OA\Get(
        path: '/member/points/{id}',
        summary: '详情',
        tags: ['会员积分'],
        x: [
            SchemaConstants::X_PROPERTY_IN => 'id',
            SchemaConstants::X_SCHEMA_REQUEST => IdRequest::class,
        ]
    )]
      #[Permission("member:points:read")]

    public function show(Request $request): \support\Response
    {
        try {
            $id   = $request->route->param('id');
            $data = $this->service->get($id, ['*'], ['member'], 'created_at', []);
            if (empty($data)) {
                throw new AdminException('数据未找到', 400);
            }
            return Json::success('ok', $data->toArray());
        } catch (\Throwable $e) {
            return Json::fail($e->getMessage(), [], $e->getCode());
        }
    }

    #[OA\Post(
        path: '/member/points',
        summary: '新增',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(properties: [
                new OA\Property(property: "member_id", type: "integer", example: 1),
                new OA\Property(property: "points", type: "integer", example: 100),
                new OA\Property(property: "type", type: "integer", example: 1),
                new OA\Property(property: "source", type: "string", example: "admin"),
                new OA\Property(property: "remark", type: "string", example: "管理员操作"),
                new OA\Property(property: "operator", type: "string", example: "admin"),
            ])
        ),
        tags: ['会员积分'],
        responses: [
            new OA\Response(
                response: 200,
                description: "成功",
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: "code", type: "integer", example: 0),
                    new OA\Property(property: "msg", type: "string", example: "操作成功"),
                ])
            ),
        ]
    )]
    #[Permission("member:points:create")]
    public function store(Request $request): \support\Response
    {
        try {
            $data = $request->all();
            if (isset($this->validate) && $this->validate) {
                if (!$this->validate->scene('store')->check($data)) {
                    throw new \Exception($this->validate->getError());
                }
            }
            $this->service->operate($data);
            return Json::success('操作成功');
        } catch (\Throwable $e) {
            return Json::fail($e->getMessage(), [], $e->getCode());
        }
    }

}
