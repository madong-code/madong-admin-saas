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
use app\adminapi\validate\member\MemberLevelValidate;
use app\schema\request\BatchDeleteRequest;
use app\schema\request\IdRequest;
use app\service\admin\member\MemberLevelService;
use madong\swagger\annotation\response\SimpleResponse;
use madong\swagger\attribute\Permission;
use OpenApi\Attributes as OA;
use support\Request;
use support\annotation\Middleware;
use WebmanTech\Swagger\DTO\SchemaConstants;

#[Middleware(AccessTokenMiddleware::class, PermissionMiddleware::class, OperationMiddleware::class)]
final class LevelController extends Crud
{

    public function __construct(MemberLevelService $service, MemberLevelValidate $validate)
    {
        $this->service  = $service;
        $this->validate = $validate;
    }

    #[OA\Get(
        path: '/member/level',
        summary: '列表',
        tags: ['会员等级'],
        parameters: [
            new OA\Parameter(name: "name", description: "等级名称", in: "query", schema: new OA\Schema(type: "string")),
            new OA\Parameter(name: "status", description: "状态", in: "query", schema: new OA\Schema(type: "integer")),
            new OA\Parameter(name: "page", description: "页码", in: "query", schema: new OA\Schema(type: "integer")),
            new OA\Parameter(name: "limit", description: "每页数量", in: "query", schema: new OA\Schema(type: "integer")),
        ]
    )]
    #[Permission("member:level:list")]
    public function index(Request $request): \support\Response
    {
        return parent::index($request);
    }

    #[OA\Post(
        path: '/member/level',
        summary: '创建',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(properties: [
                new OA\Property(property: "name", type: "string", example: "普通会员"),
                new OA\Property(property: "min_points", type: "integer", example: 0),
                new OA\Property(property: "max_points", type: "integer", example: 999),
                new OA\Property(property: "discount", type: "number", example: 1),
                new OA\Property(property: "status", type: "integer", example: 1),
                new OA\Property(property: "sort", type: "integer", example: 100),
            ])
        ),
        tags: ['会员等级'],
    )]
    #[Permission("member:level:create")]
    #[SimpleResponse(example: ['code' => 0, 'message' => 'success', 'data' => []])]
    public function store(Request $request): \support\Response
    {
        return parent::store($request);
    }

    #[OA\Put(
        path: '/member/level/{id}',
        summary: '更新',
        tags: ['会员等级'],
        x: [
            SchemaConstants::X_PROPERTY_IN    => 'id',
            SchemaConstants::X_SCHEMA_REQUEST => IdRequest::class,
        ]
    )]
    #[Permission("member:level:update")]
    #[SimpleResponse(example: ['code' => 0, 'message' => 'success', 'data' => []])]
    public function update(Request $request): \support\Response
    {
        return parent::update($request);
    }

    #[OA\Delete(
        path: '/member/level/{id}',
        summary: '删除会员等级',
        tags: ['会员等级'],
        x: [
            SchemaConstants::X_PROPERTY_IN    => 'id',
            SchemaConstants::X_SCHEMA_REQUEST => IdRequest::class,
        ]
    )]
    #[Permission("member:level:delete")]
    #[SimpleResponse(example: ['code' => 0, 'message' => 'success', 'data' => []])]
    public function destroy(Request $request): \support\Response
    {
        return parent::destroy($request);
    }

    #[OA\Delete(
        path: '/member/level',
        summary: '批量删除',
        tags: ['会员等级'],
        x: [SchemaConstants::X_SCHEMA_REQUEST => BatchDeleteRequest::class],
    )]
    #[Permission("member:level:delete")]
    #[SimpleResponse(example: ['code' => 0, 'message' => 'success', 'data' => []])]
    public function batchDelete(Request $request): \support\Response
    {
        return parent::destroy($request);
    }

    #[OA\Get(
        path: '/member/level/{id}',
        summary: '详情',
        tags: ['会员等级'],
        x: [
            SchemaConstants::X_PROPERTY_IN    => 'id',
            SchemaConstants::X_SCHEMA_REQUEST => IdRequest::class,
        ]
    )]
    #[Permission("member:level:show")]
    public function show(Request $request): \support\Response
    {
        return parent::show($request);
    }

}
