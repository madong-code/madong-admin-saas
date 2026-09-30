<?php
declare(strict_types=1);

/**
 *+------------------
 * madong - 消息模板控制器
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
use app\adminapi\validate\content\message\template\TemplateValidate;
use app\schema\request\BatchDeleteRequest;
use app\schema\request\IdRequest;
use app\service\admin\content\message\TemplateService;
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
final class TemplateController extends Crud
{
    public function __construct(TemplateService $service, TemplateValidate $validate)
    {
        $this->service  = $service;
        $this->validate = $validate;
    }

    #[OA\Get(
        path: '/content/message/template',
        summary: '消息模板列表',
        tags: ['消息模板'],
    )]
    #[Permission(code: 'message:template:list')]
    #[PageResponse(example: [])]
    public function index(Request $request): \support\Response
    {
        $definitionId = $request->input('EQ_definition_id');
        if ($definitionId) {
            try {
                [$where, $_format, $limit, $_field, $order, $page] = $this->selectInput($request);

                /** @var \Illuminate\Database\Eloquent\Builder $query */
                $query = $this->service->getModel()->query()
                    ->join('sys_message_definition_template', 'sys_message_template.id', '=', 'sys_message_definition_template.template_id')
                    ->where('sys_message_definition_template.definition_id', $definitionId);

                $this->applyWhereToQuery($query, $where);

                $total = $query->count();

                $query->select('sys_message_template.*');
                if ($order) {
                    $query->orderByRaw($order);
                } else {
                    $query->orderBy('sys_message_template.id', 'desc');
                }

                if ($page > 0 && $limit > 0) {
                    $items = $query->skip(($page - 1) * $limit)->take($limit)->get();
                } else {
                    $items = $query->get();
                }

                return Json::success('ok', compact('items', 'total'));
            } catch (\Throwable $e) {
                return Json::fail($e->getMessage());
            }
        }

        return parent::index($request);
    }

    #[OA\Get(
        path: '/content/message/template/{id}',
        summary: '消息模板详情',
        tags: ['消息模板'],
        x: [
            SchemaConstants::X_PROPERTY_IN    => 'id',
            SchemaConstants::X_SCHEMA_REQUEST => IdRequest::class,
        ]
    )]
    #[Permission(code: 'message:template:read')]
    #[DataResponse(example: [])]
    public function show(Request $request): \support\Response
    {
        return parent::show($request);
    }

    #[OA\Post(
        path: '/content/message/template',
        summary: '创建消息模板',
        tags: ['消息模板'],
    )]
    #[Permission(code: 'message:template:create')]
    #[SimpleResponse(example: ['code' => 0, 'message' => 'success', 'data' => []])]
    public function store(Request $request): \support\Response
    {
        try {
            $data = $this->insertInput($request);
            if (isset($this->validate) && $this->validate) {
                if (!$this->validate->scene('store')->check($data)) {
                    throw new \Exception($this->validate->getError());
                }
            }

            $definitionId = $request->input('definition_id');
            $model        = $this->service->saveTemplate($data, $definitionId);

            if (empty($model)) {
                throw new \Exception('插入失败');
            }
            $pk = $model->getPk();
            return Json::success('ok', [$pk => $model->getAttribute($pk)]);
        } catch (\Throwable $e) {
            return Json::fail($e->getMessage());
        }
    }

    #[OA\Put(
        path: '/content/message/template/{id}',
        summary: '更新消息模板',
        tags: ['消息模板'],
    )]
    #[OA\Parameter(
        name: 'id',
        description: '模板ID',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'string', example: '123456789012345678')
    )]
    #[Permission(code: 'message:template:update')]
    #[SimpleResponse(example: ['code' => 0, 'message' => 'success', 'data' => []])]
    public function update(Request $request): \support\Response
    {
        return parent::update($request);
    }

    #[OA\Delete(
        path: '/content/message/template/{id}',
        summary: '删除消息模板',
        tags: ['消息模板'],
        x: [
            SchemaConstants::X_PROPERTY_IN    => 'id',
            SchemaConstants::X_SCHEMA_REQUEST => IdRequest::class,
        ]
    )]
    #[Permission(code: 'message:template:delete')]
    #[SimpleResponse(example: ['code' => 0, 'message' => 'success', 'data' => []])]
    public function destroy(Request $request): \support\Response
    {
        try {
            $ids = $this->getDeleteIds($request);
            if (empty($ids)) {
                throw new \Exception('删除参数不能为空');
            }
            // 检查模板是否被关联（服务层抛异常则被阻止）
            $this->service->checkCanDelete($ids);
            return parent::destroy($request);
        } catch (\Throwable $e) {
            return Json::fail($e->getMessage());
        }
    }

    #[OA\Delete(
        path: '/content/message/template',
        summary: '批量删除消息模板',
        tags: ['消息模板'],
        x: [
            SchemaConstants::X_SCHEMA_REQUEST => BatchDeleteRequest::class,
        ]
    )]
    #[Permission(code: 'message:template:delete')]
    #[SimpleResponse(example: ['code' => 0, 'message' => 'success', 'data' => []])]
    public function batchDelete(Request $request): \support\Response
    {
        try {
            $ids = $this->getDeleteIds($request);
            if (empty($ids)) {
                throw new \Exception('删除参数不能为空');
            }
            // 检查模板是否被关联（服务层抛异常则被阻止）
            $this->service->checkCanDelete($ids);
            return parent::destroy($request);
        } catch (\Throwable $e) {
            return Json::fail($e->getMessage());
        }
    }
}
