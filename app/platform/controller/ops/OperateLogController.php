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

namespace app\platform\controller\ops;

use app\platform\controller\Base;
use app\platform\middleware\AccessTokenMiddleware;
use app\platform\middleware\OperationMiddleware;
use app\platform\schema\request\ops\OperateLogQueryRequest;
use app\platform\schema\response\ops\OperateLogResponse;
use app\service\admin\ops\logs\OperateLogService;
use core\foundation\tool\Json;
use madong\swagger\attribute\Permission;
use OpenApi\Attributes as OA;
use support\Request;
use support\annotation\Middleware;
use WebmanTech\Swagger\DTO\SchemaConstants;

#[Middleware(AccessTokenMiddleware::class, OperationMiddleware::class)]
final class OperateLogController extends Base
{
    public function __construct(OperateLogService $service)
    {
        $this->service = $service;
    }

    #[OA\Get(
        path: '/monitor/operation-log',
        summary: '列表',
        tags: ['日志管理.操作'],
        x: [
            SchemaConstants::X_SCHEMA_REQUEST => OperateLogQueryRequest::class,
        ]
    )]
    #[Permission(code: 'platform:monitor:operation-log:list')]
    #[OA\Response(
        response: 200,
        description: '成功',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'code', type: 'integer', example: 0),
                new OA\Property(property: 'data', ref: OperateLogResponse::class),
            ]
        )
    )]
    public function index(Request $request): \support\Response
    {
        try {
            [$where, $format, $limit, $field, $order, $page] = $this->selectInput($request);
            $where['filters']    = array_merge($where['filters'] ?? [], ['app' => 'eq:platform']);
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
        path: '/monitor/operation-log/{id}',
        summary: '详情',
        tags: ['日志管理.操作'],
    )]
    #[OA\Parameter(
        name: 'id',
        description: '日志ID',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'string', example: 1)
    )]
    #[Permission(code: 'platform:monitor:operation-log:view')]
    public function show(Request $request): \support\Response
    {
        return parent::show($request);
    }

    #[OA\Delete(
        path: '/monitor/operation-log/{id}',
        summary: '删除',
        tags: ['日志管理.操作'],
    )]
    #[Permission(code: 'platform:monitor:operation-log:delete')]
    public function destroy(Request $request): \support\Response
    {
        return parent::destroy($request);
    }

    #[OA\Delete(
        path: '/monitor/operation-log',
        summary: '批量删除',
        tags: ['日志管理.操作'],
    )]
    #[Permission(code: 'platform:monitor:operation-log:delete')]
    public function batchDelete(Request $request): \support\Response
    {
        return parent::destroy($request);
    }
}
