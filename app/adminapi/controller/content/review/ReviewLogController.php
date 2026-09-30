<?php
declare(strict_types=1);

/**
 *+------------------
 * madong - 审核操作日志控制器（只读）
 *+------------------
 * Copyright (c) https://gitee.com/motion-code  All rights reserved.
 *+------------------
 * Author: Mr. April (405784684@qq.com)
 *+------------------
 * Official Website: https://madong.tech
 */

namespace app\adminapi\controller\content\review;


use app\adminapi\controller\Crud;
use app\adminapi\middleware\AccessTokenMiddleware;
use app\adminapi\middleware\OperationMiddleware;
use app\adminapi\middleware\PermissionMiddleware;
use app\service\admin\content\review\log\ReviewLogService;
use core\foundation\tool\Json;
use madong\swagger\annotation\response\PageResponse;
use madong\swagger\attribute\Permission;
use OpenApi\Attributes as OA;
use support\annotation\Middleware;
use support\Request;
use support\Response;

#[Middleware(AccessTokenMiddleware::class, PermissionMiddleware::class, OperationMiddleware::class)]
final class ReviewLogController extends Crud
{
    public function __construct(ReviewLogService $service)
    {
        $this->service = $service;
    }

    /**
     * 审核操作日志列表
     */
    #[OA\Get(path: '/content/review/log', summary: '审核操作日志列表', tags: ['审核操作日志'])]
    #[Permission(code: 'content:review:log:list')]
    #[PageResponse(example: [])]
    public function index(Request $request): Response
    {
        try {
            $page  = (int)$request->input('page', 1);
            $limit = (int)$request->input('limit', 20);
            $where = $this->buildListWhere($request);

            $result = $this->service->getList($where, $page, $limit);

            return $this->formatNormal($result['list'], $result['total']);
        } catch (\Throwable $e) {
            return Json::fail($e->getMessage());
        }
    }

    /**
     * 批量删除操作日志
     */
    #[OA\Delete(path: '/content/review/log', summary: '删除操作日志', tags: ['审核操作日志'])]
    #[Permission(code: 'content:review:log:delete')]
    #[DataResponse(example: [])]
    public function destroy(Request $request): Response
    {
        return parent::destroy($request);
    }

    /**
     * 清理指定天数之前的操作日志
     */
    #[OA\Post(path: '/content/review/log/clean', summary: '清理操作日志', tags: ['审核操作日志'])]
    #[Permission(code: 'content:review:log:clean')]
    #[DataResponse(example: ['count' => 0])]
    public function clean(Request $request): Response
    {
        try {
            $days = max(1, (int)$request->input('days', 90));
            $count = $this->service->cleanBeforeDays($days);
            return Json::success('common.operation.success', ['count' => $count]);
        } catch (\Throwable $e) {
            return Json::fail($e->getMessage());
        }
    }

    /**
     * 构建列表查询条件
     */
    protected function buildListWhere(Request $request): array
    {
        $where = [];
        if ($reviewId = $request->input('review_id')) {
            $where['review_id'] = $reviewId;
        }
        if ($action = $request->input('action')) {
            $where['action'] = $action;
        }
        if ($operatorId = $request->input('operator_id')) {
            $where['operator_id'] = $operatorId;
        }
        return $where;
    }
}
