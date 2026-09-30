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
use app\adminapi\schema\request\system\RecycleBinQueryRequest;
use app\adminapi\schema\response\system\RecycleBinResponse;
use app\schema\request\BatchDeleteRequest;
use app\schema\request\IdRequest;
use app\service\admin\system\recycle\RecycleBinService;
use core\foundation\tool\Json;
use madong\swagger\annotation\response\PageResponse;
use madong\swagger\annotation\response\SimpleResponse;
use madong\swagger\attribute\Permission;
use OpenApi\Attributes as OA;
use support\Request;
use support\annotation\Middleware;
use WebmanTech\Swagger\DTO\SchemaConstants;

#[Middleware(AccessTokenMiddleware::class, PermissionMiddleware::class, OperationMiddleware::class)]
final class RecycleBinController extends Crud
{
    public function __construct(RecycleBinService $service)
    {
        $this->service = $service;
    }

    #[OA\Get(
        path: '/system/recycle',
        summary: '列表',
        tags: ['数据回收站'],
        x: [
            SchemaConstants::X_SCHEMA_REQUEST => RecycleBinQueryRequest::class,
        ]
    )]
    #[Permission(code: 'system:recycle:list')]
    #[PageResponse(schema: RecycleBinResponse::class, example: [])]
    public function index(Request $request): \support\Response
    {
        return parent::index($request);
    }

    #[OA\Get(
        path: '/system/recycle/{id}',
        summary: '详情',
        tags: ['数据回收站'],
        x: [
            SchemaConstants::X_PROPERTY_IN => 'id',
            SchemaConstants::X_SCHEMA_REQUEST => IdRequest::class,
        ]
    )]
    #[Permission(code: 'system:recycle:read')]
    #[SimpleResponse(schema: RecycleBinResponse::class, example: [])]
    public function show(Request $request): \support\Response
    {
        return parent::show($request);
    }

    #[OA\Delete(
        path: '/system/recycle/{id}',
        summary: '删除',
        tags: ['数据回收站'],
        x: [
            SchemaConstants::X_PROPERTY_IN => 'id',
            SchemaConstants::X_SCHEMA_REQUEST => IdRequest::class,
        ]
    )]
    #[Permission(code: 'system:recycle:delete')]
    #[SimpleResponse(schema: [], example: [])]
    public function destroy(Request $request): \support\Response
    {
        return parent::destroy($request);
    }

    #[OA\Delete(
        path: '/system/recycle',
        summary: '批量删除',
        tags: ['数据回收站'],
        x: [
            SchemaConstants::X_SCHEMA_REQUEST => BatchDeleteRequest::class,
        ]
    )]
    #[Permission(code: 'system:recycle:delete')]
    #[SimpleResponse(schema: [], example: [])]
    public function batchDelete(Request $request): \support\Response
    {
        return parent::destroy($request);
    }

    #[OA\Put(
        path: '/system/recycle/{id}/restore',
        summary: '恢复',
        tags: ['数据回收站'],
        x: [
            SchemaConstants::X_PROPERTY_IN => 'id',
            SchemaConstants::X_SCHEMA_REQUEST => IdRequest::class,
        ]
    )]
    #[Permission(code: 'system:recycle:recover')]
    #[SimpleResponse(schema: [], example: [])]
    public function restore(Request $request): \support\Response
    {
        try {
            $id = $request->input('id');
            $this->service->restoreRecycleBin($id);
            return Json::success('ok');
        } catch (\Exception $e) {
            return Json::fail($e->getMessage());
        }
    }
}
