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

namespace app\adminapi\controller\ops;

use app\adminapi\controller\Crud;
use app\adminapi\middleware\AccessTokenMiddleware;
use app\adminapi\middleware\OperationMiddleware;
use app\adminapi\middleware\PermissionMiddleware;
use app\adminapi\schema\request\ops\logs\OperateLogQueryRequest;
use app\adminapi\schema\response\ops\logs\OperateLogResponse;
use app\schema\request\BatchDeleteRequest;
use app\schema\request\IdRequest;
use app\service\admin\ops\logs\OperateLogService;
use madong\swagger\annotation\response\PageResponse;
use madong\swagger\annotation\response\SimpleResponse;
use madong\swagger\attribute\Permission;
use OpenApi\Attributes as OA;
use support\Request;
use support\annotation\Middleware;
use WebmanTech\Swagger\DTO\SchemaConstants;

#[Middleware(AccessTokenMiddleware::class, PermissionMiddleware::class, OperationMiddleware::class)]
final class OperateLogController extends Crud
{
    public function __construct(OperateLogService $service)
    {
        $this->service = $service;
    }

    #[OA\Get(
        path: '/ops/operate-log',
        summary: '列表',
        tags: ['日志管理.操作'],
        x: [
            SchemaConstants::X_SCHEMA_REQUEST => OperateLogQueryRequest::class,
        ]
    )]
    #[Permission(code: 'logs:operate:list')]
    #[PageResponse(schema: OperateLogResponse::class, example: [])]
    public function index(Request $request): \support\Response
    {
        return parent::index($request);
    }

    #[OA\Get(
        path: '/ops/operate-log/{id}',
        summary: '详情',
        tags: ['日志管理.操作'],
        x: [
            SchemaConstants::X_PROPERTY_IN => 'id',
            SchemaConstants::X_SCHEMA_REQUEST => IdRequest::class,
        ]
    )]
    #[Permission(code: 'logs:operate:read')]
    #[SimpleResponse(schema: OperateLogResponse::class, example: [])]
    public function show(Request $request): \support\Response
    {
        return parent::show($request);
    }

    #[OA\Delete(
        path: '/logs/operate/{id}',
        summary: '删除',
        tags: ['日志管理.操作'],
        x: [
            SchemaConstants::X_PROPERTY_IN => 'id',
            SchemaConstants::X_SCHEMA_REQUEST => IdRequest::class,
        ]
    )]
    #[OA\Parameter(
        name: 'id',
        description: 'ID',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'string', default: '123456789012345678'),
    )]
    #[Permission(code: 'logs:operate:delete')]
    #[SimpleResponse(schema: [], example: [])]
    public function destroy(Request $request): \support\Response
    {
        return parent::destroy($request);
    }

    #[OA\Delete(
        path: '/ops/operate-log',
        summary: '批量删除',
        tags: ['日志管理.操作'],
        x: [
            SchemaConstants::X_SCHEMA_REQUEST => BatchDeleteRequest::class,
        ]
    )]
    #[Permission(code: 'logs:operate:delete')]
    #[SimpleResponse(schema: [], example: [])]
    public function batchDelete(Request $request): \support\Response
    {
        return parent::destroy($request);
    }
}
