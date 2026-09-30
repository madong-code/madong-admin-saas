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
use app\adminapi\schema\request\ops\gateway\RateLimiterFormRequest;
use app\adminapi\schema\request\ops\gateway\RateLimiterQueryRequest;
use app\adminapi\schema\response\ops\gateway\RateLimiterResponse;
use app\adminapi\validate\ops\gateway\RateLimiterValidate;
use app\schema\request\BatchDeleteRequest;
use app\schema\request\IdRequest;
use app\schema\response\Result;
use app\service\admin\ops\gateway\RetaLimiterService;
use core\foundation\exception\handler\AdminException;
use core\foundation\tool\Json;
use madong\swagger\annotation\response\DataResponse;
use madong\swagger\annotation\response\PageResponse;
use madong\swagger\annotation\response\SimpleResponse;
use madong\swagger\attribute\Permission;
use OpenApi\Attributes as OA;
use support\Request;
use support\annotation\Middleware;
use WebmanTech\Swagger\DTO\SchemaConstants;

#[Middleware(AccessTokenMiddleware::class, PermissionMiddleware::class, OperationMiddleware::class)]
final class RateLimiterController extends Crud
{

    public function __construct(RetaLimiterService $service, RateLimiterValidate $validate)
    {
        $this->service  = $service;
        $this->validate = $validate;
    }

    #[OA\Get(
        path: '/ops/rate-limiter',
        summary: '列表',
        tags: ['限访规则'],
        x: [
            SchemaConstants::X_SCHEMA_REQUEST => RateLimiterQueryRequest::class,
        ]
    )]
    #[Permission(code: 'gateway:limiter:list')]
    #[PageResponse(schema: RateLimiterResponse::class, example: [])]
    public function index(Request $request): \support\Response
    {
        return parent::index($request);
    }

    #[OA\Get(
        path: '/ops/rate-limiter/{id}',
        summary: '详情',
        tags: ['限访规则'],
        x: [
            SchemaConstants::X_PROPERTY_IN => 'id',
            SchemaConstants::X_SCHEMA_REQUEST => IdRequest::class,
        ]
    )]
    #[Permission(code: 'gateway:limiter:read')]
    #[DataResponse(schema: RateLimiterResponse::class, example: [])]
    public function show(Request $request): \support\Response
    {
        return parent::show($request);
    }

    #[OA\Post(
        path: '/ops/rate-limiter',
        summary: '保存',
        tags: ['限访规则'],
        x: [
            SchemaConstants::X_SCHEMA_REQUEST => RateLimiterFormRequest::class,
        ]
    )]
    #[Permission(code: 'gateway:limiter:create')]
    #[SimpleResponse(schema: [], example: [])]
    public function store(Request $request): \support\Response
    {
        try {
            $data = $this->insertInput($request);
            if (isset($this->validate) && $this->validate) {
                if (!$this->validate->scene('store')->check($data)) {
                    throw new \Exception($this->validate->getError());
                }
            }
            //这里对入库的start_date  end_date   转换时间戳 合并到start_time  end_time
            if (isset($data['start_date']) && !empty($data['start_date'])) {
                $data['start_time'] = strtotime($data['start_date']);
                unset($data['start_date']);
            }
            if (isset($data['end_date']) && !empty($data['end_date'])) {
                $data['end_time'] = strtotime($data['end_date']);
                unset($data['end_date']);
            }

            $model = $this->service->save($data);
            if (empty($model)) {
                throw new AdminException('插入失败');
            }
            $pk = $model->getPk();
            //添加后移除缓存
            $this->service->cacheDriver()->delete(RetaLimiterService::CACHE_KEY);
            return Json::success('ok', [$pk => $model->getData($pk)]);
        } catch (\Throwable $e) {
            return Json::fail($e->getMessage());
        }
    }

    #[OA\Put(
        path: '/ops/rate-limiter/{id}/change-status',
        summary: '更新状态',
        tags: ['限访规则'],
        x: [
            SchemaConstants::X_PROPERTY_IN => 'id',
            SchemaConstants::X_SCHEMA_REQUEST => IdRequest::class,
        ]
    )]
    #[Permission(code: 'gateway:limiter:status')]
    #[SimpleResponse(schema: [], example: [])]
    public function changeStatus(Request $request): \support\Response
    {
        try {
            $data       = $this->insertInput($request);
            $model      = $this->service->getModel();
            $primaryKey = $model->getKeyName();
            if (!array_key_exists($primaryKey, $data)) {
                throw new \Exception('参数异常缺少主键');
            }
            $targetModel = $model->findOrFail($data[$primaryKey]);
            if (empty($targetModel)) {
                throw new \Exception('资源不存在' . $primaryKey . '=', $data[$primaryKey]);
            }
            $targetModel->fill($data);
            if (!$targetModel->save()) {
                throw new \RuntimeException('数据保存失败');
            }
            //清空缓存
            $this->service->cacheDriver()->delete(RetaLimiterService::CACHE_KEY);
            return Json::success('ok', []);
        } catch (\Throwable $e) {
            return Json::fail($e->getMessage());
        }
    }

    #[OA\Put(
        path: '/ops/rate-limiter/{id}',
        summary: '更新',
        tags: ['限访规则'],
        x: [
            SchemaConstants::X_SCHEMA_REQUEST => RateLimiterFormRequest::class,
        ]
    )]
    #[OA\Parameter(
        name: 'id',
        description: '限访规则ID',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'string', example: '247298887944531968')
    )]
    #[Permission(code: 'gateway:limiter:update')]
    #[SimpleResponse(schema: [], example: [])]
    public function update(Request $request): \support\Response
    {
        try {
            $data = $this->insertInput($request);
            if (isset($this->validate) && $this->validate) {
                if (!$this->validate->scene('update')->check($data)) {
                    throw new \Exception($this->validate->getError());
                }
            }
            //这里对入库的start_date  end_date   转换时间戳 合并到start_time  end_time
            if (isset($data['start_date']) && !empty($data['start_date'])) {
                $data['start_time'] = strtotime($data['start_date']);
                unset($data['start_date']);
            }
            if (isset($data['end_date']) && !empty($data['end_date'])) {
                $data['end_time'] = strtotime($data['end_date']);
                unset($data['end_date']);
            }
            $this->service->update($data['id'], $data);
            //清空缓存
            $this->service->cacheDriver()->delete(RetaLimiterService::CACHE_KEY);
            return Json::success('ok', []);
        } catch (\Throwable $e) {
            return Json::fail($e->getMessage());
        }
    }

    #[OA\Delete(
        path: '/ops/rate-limiter/{id}',
        summary: '删除',
        tags: ['限访规则'],
        x: [
            SchemaConstants::X_PROPERTY_IN => 'id',
            SchemaConstants::X_SCHEMA_REQUEST => IdRequest::class,
        ]
    )]
    #[Permission(code: 'gateway:limiter:delete')]
    #[SimpleResponse(schema: [], example: [])]
    public function destroy(Request $request): \support\Response
    {
        try {
            $data = $this->getDeleteIds($request);
            if (empty($data)) {
                throw new AdminException('参数错误');
            }
            $result = $this->service->transaction(function () use ($data) {
                $data       = is_array($data) ? $data : explode(',', $data);
                $deletedIds = [];
                foreach ($data as $id) {
                    $item = $this->service->get($id);
                    if (!$item) {
                        continue; // 如果找不到项，跳过
                    }
                    $item->delete();
                    $primaryKey   = $item->getPk();
                    $deletedIds[] = $item->{$primaryKey};
                }
                return $deletedIds;
            });
            //清空缓存
            $this->service->cacheDriver()->delete(RetaLimiterService::CACHE_KEY);
            return Json::success('ok', $result);
        } catch (\Throwable $e) {
            return Json::fail($e->getMessage());
        }
    }

    #[OA\Delete(
        path: '/ops/rate-limiter',
        summary: '批量删除',
        tags: ['限访规则'],
        x: [
            SchemaConstants::X_SCHEMA_REQUEST => BatchDeleteRequest::class,
        ]
    )]
    #[Permission(code: 'gateway:limiter:delete')]
    #[SimpleResponse(schema: [], example: [])]
    public function batchDelete(Request $request): \support\Response
    {
        try {
            $data = $this->getDeleteIds($request);
            if (empty($data)) {
                throw new AdminException('参数错误');
            }
            $result = $this->service->transaction(function () use ($data) {
                $data       = is_array($data) ? $data : explode(',', $data);
                $deletedIds = [];
                foreach ($data as $id) {
                    $item = $this->service->get($id);
                    if (!$item) {
                        continue; // 如果找不到项，跳过
                    }
                    $item->delete();
                    $primaryKey   = $item->getPk();
                    $deletedIds[] = $item->{$primaryKey};
                }
                return $deletedIds;
            });
            //清空缓存
            $this->service->cacheDriver()->delete(RetaLimiterService::CACHE_KEY);
            return Json::success('ok', $result);
        } catch (\Throwable $e) {
            return Json::fail($e->getMessage());
        }
    }

}
