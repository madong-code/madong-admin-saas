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
use app\adminapi\schema\request\ops\crontab\CrontabFormRequest;
use app\adminapi\schema\request\ops\crontab\CrontabQueryRequest;
use app\adminapi\schema\response\ops\crontab\CrontabResponse;
use app\adminapi\validate\ops\crontab\CrontabValidate;
use app\enum\system\OperationResult;
use app\schema\request\BatchDeleteRequest;
use app\schema\request\IdRequest;
use app\service\admin\ops\crontab\CrontabService;
use core\foundation\exception\handler\AdminException;
use core\foundation\tool\Json;
use madong\helper\Arr;
use madong\swagger\annotation\response\DataResponse;
use madong\swagger\annotation\response\PageResponse;
use madong\swagger\annotation\response\SimpleResponse;
use madong\swagger\attribute\Permission;
use OpenApi\Attributes as OA;
use support\Request;
use support\annotation\Middleware;
use WebmanTech\Swagger\DTO\SchemaConstants;

#[Middleware(AccessTokenMiddleware::class, PermissionMiddleware::class, OperationMiddleware::class)]
final class CrontabController extends Crud
{

    public function __construct(CrontabService $service, CrontabValidate $validate)
    {
        $this->service  = $service;
        $this->validate = $validate;
    }

    #[OA\Get(
        path: '/ops/crontab',
        summary: '列表',
        tags: ['定时任务'],
        x: [
            SchemaConstants::X_SCHEMA_REQUEST => CrontabQueryRequest::class,
        ]
    )]
        #[Permission(code: 'crontab:task:list')]
    #[PageResponse(CrontabResponse::class, example: [])]
    public function index(Request $request): \support\Response
    {
        return parent::index($request);
    }

    #[OA\Get(
        path: '/ops/crontab/{id}',
        summary: '详情',
        tags: ['定时任务'],
        x: [
            SchemaConstants::X_PROPERTY_IN => 'id',
            SchemaConstants::X_SCHEMA_REQUEST => IdRequest::class,
        ]
    )]
    #[Permission(code: 'crontab:task:read')]
    #[DataResponse(schema: CrontabResponse::class, example: [])]
    public function show(Request $request): \support\Response
    {
        try {
            $id   = $request->route->param('id');
            $data = $this->service->get($id);
            if (empty($data)) {
                throw new AdminException('数据未找到', 400);
            }

            $dataArray = $data->toArray();
            if (!empty($dataArray['cycle_rule'])) {
                $cycleRule = $dataArray['cycle_rule'];
                if (is_array($cycleRule)) {
                    $cycleRule = array_map(function ($value) {
                        if (is_string($value) && $value !== '' && is_numeric($value)) {
                            return intval($value);
                        }
                        return $value;
                    }, $cycleRule);
                    $dataArray = array_merge($dataArray, $cycleRule);
                }
                unset($dataArray['cycle_rule']);
            }

            return Json::success('ok', $dataArray);
        } catch (\Throwable $e) {
            return Json::fail($e->getMessage());
        }
    }

    #[OA\Post(
        path: '/ops/crontab',
        summary: '添加',
        tags: ['定时任务'],
        x: [
            SchemaConstants::X_SCHEMA_REQUEST => CrontabFormRequest::class,
        ]
    )]
    #[Permission(code: 'crontab:task:create')]
    #[DataResponse(schema: CrontabResponse::class, example: [])]
    public function store(Request $request): \support\Response
    {
        try {
            $data = $this->inputFilter($request->all(), ['minute', 'hour', 'day', 'week', 'month', 'second']);

            if (isset($this->validate) && $this->validate) {
                if (!$this->validate->scene('store')->check($data)) {
                    throw new \Exception($this->validate->getError());
                }
            }
            $model = $this->service->save($data);
            if (empty($model)) {
                throw new AdminException('插入失败');
            }
            $pk = $model->getPk();
            return Json::success('ok', [$pk => $model->getData($pk)]);
        } catch (\Throwable $e) {
            return Json::fail($e->getMessage());
        }
    }

    #[OA\Delete(
        path: '/ops/crontab/{id}',
        summary: '删除',
        tags: ['定时任务'],
        x: [
            SchemaConstants::X_PROPERTY_IN => 'id',
            SchemaConstants::X_SCHEMA_REQUEST => IdRequest::class,
        ]
    )]
    #[OA\Parameter(
        name: 'id',
        description: '任务ID',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'string', example: 1)
    )]
    #[Permission(code: 'crontab:task:delete')]
    #[DataResponse(schema: [], example: [])]
    public function destroy(Request $request): \support\Response
    {
        try {
            $id   = $request->route->param('id');
            $data = $request->input('data', []);
            $data = !empty($id) && $id !== '0' ? $id : $data;
            if (empty($data)) {
                throw new AdminException('参数错误');
            }
            $this->service->destroy($data);
            return Json::success('操作成功');
        } catch (\Exception $e) {
            return Json::fail($e->getMessage());
        }
    }

    #[OA\Delete(
        path: '/ops/crontab',
        summary: '批量删除',
        tags: ['定时任务'],
        x: [
            SchemaConstants::X_SCHEMA_REQUEST => BatchDeleteRequest::class,
        ]
    )]
    #[Permission(code: 'crontab:task:delete')]
    #[SimpleResponse(schema: [], example: [])]
    public function batchDelete(Request $request): \support\Response
    {
        try {
            $data = $request->input('ids', []);
            if (empty($data)) {
                throw new AdminException('参数错误');
            }
            $this->service->destroy($data);
            return Json::success('操作成功');
        } catch (\Exception $e) {
            return Json::fail($e->getMessage());
        }
    }

    #[OA\Put(
        path: '/ops/crontab/{id}',
        summary: '更新',
        tags: ['定时任务'],
        x: [
            SchemaConstants::X_SCHEMA_REQUEST => CrontabFormRequest::class,
        ]
    )]
    #[OA\Parameter(
        name: 'id',
        description: '任务ID',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'string', example: 1)
    )]
    #[Permission(code: 'crontab:task:update')]
    #[SimpleResponse(schema: [], example: [])]
    public function update(Request $request): \support\Response
    {
        try {
            $id   = $request->route->param('id');
            $data = $this->inputFilter($request->all(), ['minute', 'hour', 'day', 'week', 'month', 'second']);
            if (isset($this->validate) && $this->validate) {
                if (!$this->validate->scene('update')->check($data)) {
                    throw new \Exception($this->validate->getError());
                }
            }
            $this->service->update($id, $data);
            return Json::success('ok', []);
        } catch (\Throwable $e) {
            return Json::fail($e->getMessage());
        }
    }

    #[OA\Put(
        path: '/ops/crontab/{id}/resume',
        summary: '恢复',
        tags: ['定时任务'],
        x: [
            SchemaConstants::X_PROPERTY_IN => 'id',
            SchemaConstants::X_SCHEMA_REQUEST => IdRequest::class,
        ]
    )]
    #[OA\Parameter(
        name: 'id',
        description: '任务ID',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'string', example: 1)
    )]
    #[Permission(code: 'crontab:task:resume')]
    #[SimpleResponse(schema: [], example: [])]
    public function resume(Request $request): \support\Response
    {
        try {
            $id = $request->route->param('id');
            $this->service->resumeCrontab(Arr::normalize($id));
            return Json::success('执行完成');
        } catch (\Exception $e) {
            return Json::fail($e->getMessage());
        }
    }

    #[OA\Put(
        path: '/ops/crontab/{id}/pause',
        summary: '暂停',
        tags: ['定时任务'],
        x: [
            SchemaConstants::X_PROPERTY_IN => 'id',
            SchemaConstants::X_SCHEMA_REQUEST => IdRequest::class,
        ]
    )]
    #[OA\Parameter(
        name: 'id',
        description: '任务ID',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'string', example: 1)
    )]
    #[Permission(code: 'crontab:task:pause')]
    #[SimpleResponse(schema: [], example: [])]
    public function pause(Request $request): \support\Response
    {
        try {
            $id = $request->route->param('id');
            $this->service->pauseCrontab($id);
            return Json::success('执行完成');
        } catch (\Exception $e) {
            return Json::fail($e->getMessage());
        }
    }

    #[OA\Put(
        path: '/ops/crontab/{id}/execute',
        summary: '执行',
        tags: ['定时任务'],
        x: [
            SchemaConstants::X_PROPERTY_IN => 'id',
            SchemaConstants::X_SCHEMA_REQUEST => IdRequest::class,
        ]
    )]
    #[Permission(code: 'crontab:task:execute')]
    #[SimpleResponse(schema: [], example: [])]
    public function execute(Request $request): \support\Response
    {
        try {
            $id     = $request->route->param('id');
            $result = $this->service->runOneTask(['id' => $id]);
            if ($result['code'] == OperationResult::FAILURE->value) {
                throw new AdminException('执行失败' . $result['log']);
            }
            return Json::success('执行完成', $result);
        } catch (\Exception $e) {
            return Json::fail($e->getMessage(), []);
        }
    }
}
