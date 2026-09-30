<?php
declare(strict_types=1);

/**
 *+------------------
 * madong - 消息管理控制器（仅消息定义）
 *+------------------
 * Copyright (c) https://gitee.com/motion-code  All rights reserved.
 *+------------------
 * Author: Mr. April (405784684@qq.com)
 *+------------------
 * Official Website: https://madong.tech
 */

namespace app\adminapi\controller\content\message;

use app\adminapi\controller\Crud;
use app\adminapi\middleware\AccessTokenMiddleware;
use app\adminapi\middleware\OperationMiddleware;
use app\adminapi\middleware\PermissionMiddleware;
use app\adminapi\schema\request\content\message\manage\ManageQueryRequest;
use app\adminapi\validate\content\message\manage\ManageValidate;
use app\model\content\message\DefinitionRel;
use app\schema\request\BatchDeleteRequest;
use app\schema\request\IdRequest;
use app\service\admin\content\message\ManageService;
use core\foundation\tool\Json;
use madong\swagger\annotation\response\DataResponse;
use madong\swagger\annotation\response\PageResponse;
use madong\swagger\annotation\response\SimpleResponse;
use madong\swagger\attribute\Permission;
use OpenApi\Attributes as OA;
use support\annotation\Middleware;
use support\Request;
use WebmanTech\Swagger\DTO\SchemaConstants;

#[Middleware(AccessTokenMiddleware::class, PermissionMiddleware::class, OperationMiddleware::class)]
final class ManageController extends Crud
{
    public function __construct(ManageService $service, ManageValidate $validate)
    {
        $this->service  = $service;
        $this->validate = $validate;
    }

    #[OA\Get(
        path: '/content/message/manage',
        summary: '消息管理列表',
        tags: ['消息管理'],
        x: [
            SchemaConstants::X_SCHEMA_REQUEST => ManageQueryRequest::class,
        ]
    )]
    #[Permission(code: 'message:manage:list')]
    #[PageResponse(example: [])]
    public function index(Request $request): \support\Response
    {
        return parent::index($request);
    }

    #[OA\Get(
        path: '/content/message/manage/{id}',
        summary: '消息管理详情',
        tags: ['消息管理'],
        x: [
            SchemaConstants::X_PROPERTY_IN => 'id',
            SchemaConstants::X_SCHEMA_REQUEST => IdRequest::class,
        ]
    )]
    #[Permission(code: 'message:manage:read')]
    #[DataResponse(example: [])]
    public function show(Request $request): \support\Response
    {
        return parent::show($request);
    }

    #[OA\Post(
        path: '/content/message/manage',
        summary: '创建消息管理',
        tags: ['消息管理'],
    )]
    #[Permission(code: 'message:manage:create')]
    #[SimpleResponse(example: ['code' => 0, 'message' => 'success', 'data' => []])]
    public function store(Request $request): \support\Response
    {
        return parent::store($request);
    }

    #[OA\Put(
        path: '/content/message/manage/{id}',
        summary: '更新消息管理',
        tags: ['消息管理'],
    )]
    #[OA\Parameter(
        name: 'id',
        description: '消息ID（雪花ID）',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'string', example: '123456789012345678')
    )]
    #[Permission(code: 'message:manage:update')]
    #[SimpleResponse(example: ['code' => 0, 'message' => 'success', 'data' => []])]
    public function update(Request $request): \support\Response
    {
        return parent::update($request);
    }

    #[OA\Delete(
        path: '/content/message/manage/{id}',
        summary: '删除消息管理（级联删除模板）',
        tags: ['消息管理'],
        x: [
            SchemaConstants::X_PROPERTY_IN => 'id',
            SchemaConstants::X_SCHEMA_REQUEST => IdRequest::class,
        ]
    )]
    #[Permission(code: 'message:manage:delete')]
    #[SimpleResponse(example: ['code' => 0, 'message' => 'success', 'data' => []])]
    public function destroy(Request $request): \support\Response
    {
        return parent::destroy($request);
    }

    #[OA\Delete(
        path: '/content/message/manage',
        summary: '批量删除消息管理',
        tags: ['消息管理'],
        x: [
            SchemaConstants::X_SCHEMA_REQUEST => BatchDeleteRequest::class,
        ]
    )]
    #[Permission(code: 'message:manage:delete')]
    #[SimpleResponse(example: ['code' => 0, 'message' => 'success', 'data' => []])]
    public function batchDelete(Request $request): \support\Response
    {
        return parent::destroy($request);
    }

    #[OA\Get(
        path: '/content/message/manage/{id}/templates',
        summary: '获取消息关联的模板列表',
        tags: ['消息管理'],
    )]
    #[Permission(code: 'message:manage:read')]
    #[DataResponse(example: [])]
    public function templateList(Request $request): \support\Response
    {
        $id = $request->route->param('id');
        $templateIds = DefinitionRel::where('definition_id', $id)
            ->pluck('template_id')
            ->map(fn($v) => (string)$v)
            ->values()
            ->toArray();
        return Json::success('ok', $templateIds);
    }

    #[OA\Post(
        path: '/content/message/manage/{id}/templates',
        summary: '同步消息关联的模板（全量覆盖）',
        tags: ['消息管理'],
    )]
    #[Permission(code: 'message:manage:update')]
    #[SimpleResponse(example: ['code' => 0, 'message' => 'success'])]
    public function templateSync(Request $request): \support\Response
    {
        $id = $request->route->param('id');
        $params = $request->post();
        $templateIds = $params['template_ids'] ?? [];

        // 全量覆盖：删旧插新
        DefinitionRel::where('definition_id', $id)->delete();
        $rows = [];
        foreach ($templateIds as $tid) {
            $rows[] = [
                'definition_id' => $id,
                'template_id'   => $tid,
            ];
        }
        if (!empty($rows)) {
            DefinitionRel::insert($rows);
        }

        return Json::success('ok');
    }
}
