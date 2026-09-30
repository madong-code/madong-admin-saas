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
use app\adminapi\schema\request\system\org\PostFormRequest;
use app\adminapi\schema\request\system\org\PostQueryRequest;
use app\adminapi\schema\response\system\PostResponse;
use app\adminapi\validate\system\org\PostValidate;
use app\schema\request\BatchDeleteRequest;
use app\schema\request\IdRequest;
use app\service\admin\system\org\PostService;
use core\foundation\exception\handler\AdminException;
use core\foundation\tool\Json;
use madong\swagger\annotation\response\PageResponse;
use madong\swagger\annotation\response\SimpleResponse;
use madong\swagger\attribute\Permission;
use OpenApi\Attributes as OA;
use support\Request;
use support\annotation\Middleware;
use WebmanTech\Swagger\DTO\SchemaConstants;

#[Middleware(AccessTokenMiddleware::class, PermissionMiddleware::class, OperationMiddleware::class)]
final class PostController extends Crud
{

    public function __construct(PostService $service, PostValidate $validate)
    {
        $this->service  = $service;
        $this->validate = $validate;
    }

    #[OA\Get(
        path: '/system/post',
        summary: '列表',
        tags: ['岗位管理'],
        x: [
            SchemaConstants::X_SCHEMA_REQUEST => PostQueryRequest::class,
        ]
    )]
    #[Permission(code: 'org:post:list')]
    #[PageResponse(PostResponse::class, example: '{"code":0,"msg":"success","data":[]}')] // 分页响应关联岗位Schema
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
            $list            = $this->service->selectList($where, $field, $page, $limit, $order, ['dept']);
            return call_user_func([$this, $format_function], $list, $total);
        } catch (\Throwable $e) {
            return Json::fail($e->getMessage());
        }
    }

    #[OA\Get(
        path: '/system/post/{id}',
        summary: '详情',
        tags: ['岗位管理'],
        x: [
            SchemaConstants::X_PROPERTY_IN    => 'id',
            SchemaConstants::X_SCHEMA_REQUEST => IdRequest::class,
        ]
    )]
    #[Permission(code: 'org:post:read')]
    #[SimpleResponse(PostResponse::class, example: [])] // 详情响应
    public function show(Request $request): \support\Response
    {
        try {
            $id   = $request->route->param('id');
            $data = $this->service->get($id,['*'],['dept']);
            if (empty($data)) {
                throw new AdminException('数据未找到');
            }
            return Json::success('ok', $data->toArray());
        } catch (\Throwable $e) {
            return Json::fail($e->getMessage());
        }
    }

    #[OA\Post(
        path: '/system/post',
        summary: '创建',
        tags: ['岗位管理'],
        x: [
            SchemaConstants::X_SCHEMA_REQUEST => PostFormRequest::class,
        ]
    )]
    #[Permission(code: 'org:post:create')]
    #[SimpleResponse(example: ['code' => 0, 'message' => 'success', 'data' => []])]
    public function store(Request $request): \support\Response
    {
        return parent::store($request);
    }

    #[OA\Put(
        path: '/system/post/{id}',
        summary: '更新',
        tags: ['岗位管理'],
        x: [
            SchemaConstants::X_PROPERTY_IN    => 'id',
            SchemaConstants::X_SCHEMA_REQUEST => PostFormRequest::class,

        ]
    )]
    #[Permission(code: 'org:post:update')]
    #[SimpleResponse(example: ['code' => 0, 'message' => 'success', 'data' => []])]
    public function update(Request $request): \support\Response
    {
        return parent::update($request);
    }

    #[OA\Delete(
        path: '/system/post/{id}',
        summary: '删除',
        tags: ['岗位管理'],
        x: [
            SchemaConstants::X_PROPERTY_IN    => 'id',
            SchemaConstants::X_SCHEMA_REQUEST => IdRequest::class,
        ]
    )]
    #[OA\Parameter(
        name: 'id',
        description: 'ID',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'string', example: '123456789012345678')
    )]
    #[Permission(code: 'org:post:delete')]
    #[SimpleResponse(example: ['code' => 0, 'message' => 'success', 'data' => []])]
    public function destroy(Request $request): \support\Response
    {
        return parent::destroy($request);
    }

    #[OA\Delete(
        path: '/system/post',
        summary: '批量删除',
        tags: ['岗位管理'],
        x: [
            SchemaConstants::X_SCHEMA_REQUEST => BatchDeleteRequest::class,
        ]
    )]
    #[OA\Parameter(
        name: 'id',
        description: 'ID',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'string', example: '123456789012345678')
    )]
    #[Permission(code: 'org:post:delete')]
    #[SimpleResponse(example: ['code' => 0, 'message' => 'success', 'data' => []])]
    public function batchDelete(Request $request): \support\Response
    {
        return parent::destroy($request);
    }
}
