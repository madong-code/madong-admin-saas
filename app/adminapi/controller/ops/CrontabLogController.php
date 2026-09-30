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
use app\adminapi\schema\request\ops\crontab\CrontabLogQueryRequest;
use app\adminapi\schema\response\ops\crontab\CrontabLogResponse;
use app\schema\request\BatchDeleteRequest;
use app\schema\request\IdRequest;
use app\service\admin\ops\crontab\CrontabLogService;
use madong\swagger\annotation\response\DataResponse;
use madong\swagger\annotation\response\PageResponse;
use madong\swagger\annotation\response\SimpleResponse;
use madong\swagger\attribute\Permission;
use OpenApi\Attributes as OA;
use support\annotation\Middleware;
use support\Request;
use WebmanTech\Swagger\DTO\SchemaConstants;

#[Middleware(AccessTokenMiddleware::class, PermissionMiddleware::class, OperationMiddleware::class)]
final class CrontabLogController extends Crud
{

    public function __construct(CrontabLogService $service)
    {
        $this->service = $service;
    }

    #[OA\Get(
        path: '/ops/crontab-log',
        summary: '列表',
        tags: ['定时任务.日志'],
        x: [
            SchemaConstants::X_SCHEMA_REQUEST => CrontabLogQueryRequest::class,
        ]
    )]
    #[Permission(code: 'crontab:log:list')]
    #[PageResponse(schema: CrontabLogResponse::class, example: [])]
    public function index(Request $request): \support\Response
    {
        return parent::index($request);
    }

    #[OA\Get(
        path: '/ops/crontab-log/{id}',
        summary: '详情',
        tags: ['定时任务.日志'],
        x: [
            SchemaConstants::X_PROPERTY_IN => 'id',
            SchemaConstants::X_SCHEMA_REQUEST => IdRequest::class,
        ]
    )]
    #[Permission(code: 'crontab:log:read')]
    #[DataResponse(schema: CrontabLogResponse::class, example: [])]
    public function show(Request $request): \support\Response
    {
        return parent::show($request);
    }

    #[OA\Delete(
        path: '/ops/crontab-log/{id}',
        summary: '删除',
        tags: ['定时任务.日志'],
        x: [
            SchemaConstants::X_PROPERTY_IN => 'id',
            SchemaConstants::X_SCHEMA_REQUEST => IdRequest::class,
        ]
    )]
    #[Permission(code: 'crontab:log:delete')]
    #[SimpleResponse(schema: [], example: [])]
    public function destroy(Request $request): \support\Response
    {
        return parent::destroy($request);
    }

    #[OA\Delete(
        path: '/ops/crontab-log',
        summary: '批量删除',
        tags: ['定时任务.日志'],
        x: [
            SchemaConstants::X_SCHEMA_REQUEST => BatchDeleteRequest::class,
        ]
    )]
    #[Permission(code: 'crontab:log:delete')]
    #[SimpleResponse(schema: [], example: [])]
    public function batchDelete(Request $request): \support\Response
    {
        return parent::destroy($request);
    }

}
