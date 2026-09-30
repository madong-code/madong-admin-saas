<?php
declare(strict_types=1);

/**
 *+------------------
 * madong - 审核记录控制器（只读）
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
use app\adminapi\schema\response\content\review\ReviewDetailResponse;
use app\adminapi\validate\content\review\ReviewValidate;
use app\enum\review\ReviewStatus;
use app\service\admin\content\review\ReviewService;
use core\foundation\tool\Json;
use madong\swagger\annotation\response\DataResponse;
use madong\swagger\annotation\response\PageResponse;
use madong\swagger\attribute\Permission;
use OpenApi\Attributes as OA;
use support\annotation\Middleware;
use support\Request;
use support\Response;

#[Middleware(AccessTokenMiddleware::class, PermissionMiddleware::class, OperationMiddleware::class)]
final class ReviewRecordController extends Crud
{
    public function __construct(ReviewService $service, ReviewValidate $validate)
    {
        $this->service  = $service;
        $this->validate = $validate;
    }

    /**
     * 审核记录列表（运行中 + 已归档），默认展示已通过/已拒绝
     */
    #[OA\Get(path: '/content/review/record', summary: '审核记录列表', tags: ['审核记录'])]
    #[Permission(code: 'content:review:record:list')]
    #[PageResponse(example: [])]
    public function index(Request $request): Response
    {
        try {
            $page  = (int)$request->input('page', 1);
            $limit = (int)$request->input('limit', 20);
            $where = $this->buildListWhere($request);
            $statusSearch = $where['IN_status'] ?? '';

            // 判断筛选的是否全为已完结状态 → 只查归档表
            $onlyFinished = false;
            $onlyActive   = false;
            $statusList = is_array($statusSearch) ? $statusSearch : ('' !== $statusSearch ? array_map('trim', explode(',', $statusSearch)) : []);
            if (!empty($statusList)) {
                $finished   = [ReviewStatus::APPROVED->value, ReviewStatus::REJECTED->value, ReviewStatus::CANCELED->value];
                $active     = [ReviewStatus::PENDING->value, ReviewStatus::PROCESSING->value];
                $onlyFinished = empty(array_diff($statusList, $finished));
                $onlyActive   = empty(array_diff($statusList, $active));
            }

            if ($onlyFinished) {
                // 只查归档表
                $total = $this->service->getArchiveCount($where);
                $list  = $this->service->getArchiveList($where, '*', $page, $limit, 'id desc');
            } elseif ($onlyActive) {
                // 只查运行表
                $total = $this->service->getCount($where);
                $list  = $this->service->getMappedList($where, '*', $page, $limit, 'id desc');
            } else {
                // 混合查询：分别取两表数据，合并后手动分页
                $activeList  = $this->service->getMappedList($where, '*', 0, 0, 'id desc');
                $archiveList = $this->service->getArchiveList($where, '*', 0, 0, 'id desc');
                $all         = collect($activeList)->merge($archiveList)->sortByDesc('id')->values();
                $total       = $all->count();
                $list        = $all->forPage($page, $limit);
            }

            return $this->formatNormal($list, $total);
        } catch (\Throwable $e) {
            return Json::fail($e->getMessage());
        }
    }

    /**
     * 归档审核记录列表（只读）
     */
    #[OA\Get(path: '/content/review/record/archive', summary: '审核记录列表(已归档)', tags: ['审核记录'])]
    #[Permission(code: 'content:review:record:archive')]
    #[PageResponse(example: [])]
    public function archive(Request $request): Response
    {
        try {
            $page  = (int)$request->input('page', 1);
            $limit = (int)$request->input('limit', 20);
            $where = $this->buildListWhere($request);

            $total = $this->service->getArchiveCount($where);
            $list  = $this->service->getArchiveList($where, '*', $page, $limit, 'id desc');

            return $this->formatNormal($list, $total);
        } catch (\Throwable $e) {
            return Json::fail($e->getMessage());
        }
    }

    /**
     * 审核记录详情（只读，聚合信息/表单/状态/事件）
     */
    #[OA\Get(
        path: '/content/review/record/{id}',
        summary: '审核记录详情',
        tags: ['审核记录'],
        x: [ReviewDetailResponse::class]
    )]
    #[Permission(code: 'content:review:record:read')]
    #[DataResponse(example: [])]
    public function detail(Request $request): Response
    {
        try {
            $id   = $request->route->param('id');
            $data = $this->service->getReviewDetail($id);
            if (empty($data)) {
                return Json::fail('审核记录不存在');
            }
            return Json::success('ok', $data);
        } catch (\Throwable $e) {
            return Json::fail($e->getMessage());
        }
    }

    /**
     * 归档审核记录详情（只读）
     */
    #[OA\Get(
        path: '/content/review/record/archive/{id}',
        summary: '审核记录详情(已归档)',
        tags: ['审核记录'],
        x: [ReviewDetailResponse::class]
    )]
    #[Permission(code: 'content:review:record:archive:read')]
    #[DataResponse(example: [])]
    public function archiveDetail(Request $request): Response
    {
        try {
            $id   = $request->route->param('id');
            $data = $this->service->getArchiveDetail($id);
            if (empty($data)) {
                return Json::fail('归档记录不存在');
            }
            return Json::success('ok', $data);
        } catch (\Throwable $e) {
            return Json::fail($e->getMessage());
        }
    }

    /**
     * 审核类型下拉
     */
    #[OA\Get(path: '/content/review/record/types', summary: '审核类型下拉', tags: ['审核记录'])]
    #[Permission(code: 'content:review:record:index')]
    #[PageResponse(example: [])]
    public function types(Request $request): Response
    {
        try {
            return Json::success('ok', $this->service->getTypes());
        } catch (\Throwable $e) {
            return Json::fail($e->getMessage());
        }
    }

    /**
     * 构建列表查询条件（含 extra_data 模糊搜索）
     */
    protected function buildListWhere(Request $request): array
    {
        $where = [];
        if ($type = $request->input('reviewable_type')) {
            $where['reviewable_type'] = $type;
        }
        if ($status = $request->input('IN_status')) {
            $where['IN_status'] = $status;
        } elseif ($status = $request->input('status')) {
            $where['IN_status'] = $status;
        }
        if ($flowType = $request->input('flow_type')) {
            $where['flow_type'] = $flowType;
        }
        if ($keyword = $request->input('keyword')) {
            $where['LIKE_title'] = $keyword;
        }
        // BETWEEN_created_at 时间范围：前端 DatePicker 传 ISO 字符串，转 Unix 秒时间戳
        if ($dateRange = $request->input('BETWEEN_created_at')) {
            if (is_array($dateRange)) {
                $where['BETWEEN_created_at'] = array_map(function ($v) {
                    if (is_string($v) && preg_match('/^\d{4}-\d{2}-\d{2}/', $v)) {
                        return strtotime($v);
                    }
                    if (is_numeric($v)) {
                        return $v > 1e11 ? intval($v / 1000) : intval($v);
                    }
                    return $v;
                }, $dateRange);
            } else {
                $where['BETWEEN_created_at'] = $dateRange;
            }
        }
        return $where;
    }
}
