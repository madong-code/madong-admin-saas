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
use app\adminapi\schema\request\system\dict\DictItemFormRequest;
use app\adminapi\schema\request\system\dict\DictItemQueryRequest;
use app\adminapi\schema\response\system\DictItemResponse;
use app\adminapi\validate\system\dict\DictItemValidate;
use app\schema\request\BatchDeleteRequest;
use app\schema\request\IdRequest;
use app\service\admin\system\dict\DictItemService;
use madong\swagger\annotation\response\DataResponse;
use madong\swagger\annotation\response\PageResponse;
use madong\swagger\annotation\response\SimpleResponse;
use madong\swagger\attribute\Permission;
use OpenApi\Attributes as OA;
use support\Request;
use support\annotation\Middleware;
use WebmanTech\Swagger\DTO\SchemaConstants;

#[Middleware(AccessTokenMiddleware::class, PermissionMiddleware::class, OperationMiddleware::class)]
final class DictItemController extends Crud
{
    public function __construct(DictItemService $service, DictItemValidate $validate)
    {
        $this->service  = $service;
        $this->validate = $validate;
    }

    #[OA\Get(
        path: '/system/dict-item',
        summary: '列表',
        tags: ['字典项管理'],
        x: [SchemaConstants::X_SCHEMA_REQUEST => DictItemQueryRequest::class]
    )]
    #[Permission(code: 'dict:item:list')]
    #[PageResponse(schema: DictItemResponse::class, example: [])]
    public function index(Request $request): \support\Response
    {
        return parent::index($request);
    }

    #[OA\Get(
        path: '/system/dict-item/{id}',
        summary: '详情',
        tags: ['字典项管理'],
        x: [
            SchemaConstants::X_PROPERTY_IN => 'id',
            SchemaConstants::X_SCHEMA_REQUEST => IdRequest::class,
        ]
    )]
    #[Permission(code: 'dict:item:read')]
    #[DataResponse(schema: DictItemResponse::class, example: [])]
    public function show(Request $request): \support\Response
    {
        return parent::show($request);
    }

    #[OA\Post(
        path: '/system/dict-item',
        summary: '新增',
        tags: ['字典项管理'],
        x: [SchemaConstants::X_SCHEMA_REQUEST => DictItemFormRequest::class]
    )]
    #[Permission(code: 'dict:item:create')]
    #[SimpleResponse(schema: [], example: [])]
    public function store(Request $request): \support\Response
    {
        return parent::store($request);
    }

    #[OA\Put(
        path: '/system/dict-item/{id}',
        summary: '更新',
        tags: ['字典项管理'],
        x: [SchemaConstants::X_SCHEMA_REQUEST => DictItemFormRequest::class]
    )]
    #[OA\Parameter(
        name: 'id',
        description: '字典项ID',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'string', example: '1')
    )]
    #[Permission(code: 'dict:item:update')]
    #[SimpleResponse(schema: [], example: [])]
    public function update(Request $request): \support\Response
    {
        return parent::update($request);
    }

    #[OA\Delete(
        path: '/system/dict-item/{id}',
        summary: '删除',
        tags: ['字典项管理'],
        x: [
            SchemaConstants::X_PROPERTY_IN => 'id',
            SchemaConstants::X_SCHEMA_REQUEST => IdRequest::class,
        ]
    )]
    #[OA\Parameter(
        name: 'id',
        description: '字典项ID',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'string', default: '0', example: 0)
    )]
    #[Permission(code: 'dict:item:delete')]
    #[SimpleResponse(schema: [], example: [])]
    public function destroy(Request $request): \support\Response
    {
        return parent::destroy($request);
    }

    #[OA\Delete(
        path: '/system/dict-item',
        summary: '批量删除',
        tags: ['字典项管理'],
        x: [
            SchemaConstants::X_SCHEMA_REQUEST => BatchDeleteRequest::class,
        ]
    )]
    #[Permission(code: 'dict:item:delete')]
    #[SimpleResponse(schema: [], example: [])]
    public function batchDelete(Request $request): \support\Response
    {
        return parent::destroy($request);
    }
}
