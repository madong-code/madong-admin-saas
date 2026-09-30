<?php
declare(strict_types=1);

/**
 *+------------------
 * madong - 审核管理控制器
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
use app\adminapi\schema\request\content\review\ReviewApproveRequest;
use app\adminapi\schema\request\content\review\ReviewBatchRequest;
use app\adminapi\schema\request\content\review\ReviewCancelRequest;
use app\adminapi\schema\request\content\review\ReviewRejectRequest;
use app\adminapi\schema\response\content\review\ReviewDetailResponse;
use app\adminapi\validate\content\review\ReviewValidate;
use app\service\admin\content\review\ReviewService;
use core\foundation\tool\Json;
use madong\swagger\annotation\response\DataResponse;
use madong\swagger\annotation\response\PageResponse;
use madong\swagger\annotation\response\SimpleResponse;
use madong\swagger\attribute\Permission;
use OpenApi\Attributes as OA;
use support\annotation\Middleware;
use support\Request;
use support\Response;

#[Middleware(AccessTokenMiddleware::class, PermissionMiddleware::class, OperationMiddleware::class)]
final class ReviewController extends Crud
{
    public function __construct(ReviewService $service, ReviewValidate $validate)
    {
        $this->service  = $service;
        $this->validate = $validate;
    }

    /**
     * 运行中审核列表（仅 sys_review 热数据）
     */
    #[OA\Get(path: '/content/review/manage', summary: '审核列表(运行中)', tags: ['审核管理'])]
    #[Permission(code: 'content:review:manage:list')]
    #[PageResponse(example: [])]
    public function index(Request $request): Response
    {
        try {
            $page  = (int)$request->input('page', 1);
            $limit = (int)$request->input('limit', 20);
            $where = $this->buildListWhere($request);

            $total = $this->service->getCount($where);
            $list  = $this->service->getMappedList($where, '*', $page, $limit, 'id desc');

            return $this->formatNormal($list, $total);
        } catch (\Throwable $e) {
            return Json::fail($e->getMessage());
        }
    }

    /**
     * 归档审核列表（sys_review_archive 冷数据）
     */
    #[OA\Get(path: '/content/review/manage/archive', summary: '审核列表(已归档)', tags: ['审核管理'])]
    #[Permission(code: 'content:review:manage:archive')]
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
     * 审核详情（聚合：审核信息 / 表单快照 / 状态 / 事件）
     *
     * 运行中记录优先查 sys_review；已终结归档（运行表软删）自动回退 sys_review_archive，
     * 二者主键一致、事件日志同一 review_id 关联，对调用方透明。
     */
    #[OA\Get(
        path: '/content/review/manage/{id}',
        summary: '审核详情(含表单/审核信息/状态/事件)',
        tags: ['审核管理'],
        x: [ReviewDetailResponse::class]
    )]
    #[Permission(code: 'content:review:manage:read')]
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
     * 归档审核详情
     */
    #[OA\Get(
        path: '/content/review/manage/archive/{id}',
        summary: '审核详情(已归档)',
        tags: ['审核管理'],
        x: [ReviewDetailResponse::class]
    )]
    #[Permission(code: 'content:review:manage:archive:read')]
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
     * 通过审核
     */
    #[OA\Post(
        path: '/content/review/manage/{id}/approve',
        summary: '通过审核',
        tags: ['审核管理'],
        x: [ReviewApproveRequest::class]
    )]
    #[Permission(code: 'content:review:manage:approve')]
    #[SimpleResponse(example: ['code' => 0, 'message' => 'success', 'data' => []])]
    public function approve(Request $request): Response
    {
        try {
            $id     = $request->route->param('id');
            $force  = (bool)$request->input('force', false);
            $reason = $request->input('reason', '');

            $result = $this->service->approve($id, [
                'reason' => $reason,
                'force'  => $force,
            ]);
            return $result ? Json::success('审核通过') : Json::fail('审核失败');
        } catch (\Throwable $e) {
            return Json::fail($e->getMessage());
        }
    }

    /**
     * 拒绝审核
     */
    #[OA\Post(
        path: '/content/review/manage/{id}/reject',
        summary: '拒绝审核',
        tags: ['审核管理'],
        x: [ReviewRejectRequest::class]
    )]
    #[Permission(code: 'content:review:manage:reject')]
    #[SimpleResponse(example: ['code' => 0, 'message' => 'success', 'data' => []])]
    public function reject(Request $request): Response
    {
        try {
            $id     = $request->route->param('id');
            $force  = (bool)$request->input('force', false);
            $reason = (string)$request->input('reason', '');

            if ($this->validate) {
                if (!$this->validate->scene('reject')->check(['reason' => $reason])) {
                    return Json::fail($this->validate->getError());
                }
            }

            $result = $this->service->reject($id, [
                'reason' => $reason,
                'force'  => $force,
            ]);
            return $result ? Json::success('已拒绝') : Json::fail('操作失败');
        } catch (\Throwable $e) {
            return Json::fail($e->getMessage());
        }
    }

    /**
     * 取消审核
     */
    #[OA\Post(
        path: '/content/review/manage/{id}/cancel',
        summary: '取消审核',
        tags: ['审核管理'],
        x: [ReviewCancelRequest::class]
    )]
    #[Permission(code: 'content:review:manage:cancel')]
    #[SimpleResponse(example: ['code' => 0, 'message' => 'success', 'data' => []])]
    public function cancel(Request $request): Response
    {
        try {
            $id     = $request->route->param('id');
            $reason = (string)$request->input('reason', '');

            $result = $this->service->cancel($id, ['reason' => $reason]);
            return $result ? Json::success('已取消') : Json::fail('操作失败');
        } catch (\Throwable $e) {
            return Json::fail($e->getMessage());
        }
    }

    /**
     * 批量通过
     */
    #[OA\Post(
        path: '/content/review/manage/batch-approve',
        summary: '批量通过审核',
        tags: ['审核管理'],
        x: [ReviewBatchRequest::class]
    )]
    #[Permission(code: 'content:review:manage:approve')]
    #[SimpleResponse(example: ['code' => 0, 'message' => 'success', 'data' => []])]
    public function batchApprove(Request $request): Response
    {
        try {
            $ids   = (array)$request->input('ids', []);
            $force = (bool)$request->input('force', false);
            if (empty($ids)) {
                return Json::fail('请选择审核记录');
            }
            $count = $this->service->batchApprove($ids, ['force' => $force]);
            return Json::success('已通过 ' . $count . ' 条', ['count' => $count]);
        } catch (\Throwable $e) {
            return Json::fail($e->getMessage());
        }
    }

    /**
     * 批量拒绝
     */
    #[OA\Post(
        path: '/content/review/manage/batch-reject',
        summary: '批量拒绝审核',
        tags: ['审核管理'],
        x: [ReviewBatchRequest::class]
    )]
    #[Permission(code: 'content:review:manage:reject')]
    #[SimpleResponse(example: ['code' => 0, 'message' => 'success', 'data' => []])]
    public function batchReject(Request $request): Response
    {
        try {
            $ids    = (array)$request->input('ids', []);
            $force  = (bool)$request->input('force', false);
            $reason = (string)$request->input('reason', '');
            if (empty($ids)) {
                return Json::fail('请选择审核记录');
            }
            if ($this->validate) {
                if (!$this->validate->scene('reject')->check(['reason' => $reason])) {
                    return Json::fail($this->validate->getError());
                }
            }
            $count = $this->service->batchReject($ids, ['force' => $force, 'reason' => $reason]);
            return Json::success('已拒绝 ' . $count . ' 条', ['count' => $count]);
        } catch (\Throwable $e) {
            return Json::fail($e->getMessage());
        }
    }

    /**
     * 审核类型下拉
     */
    #[OA\Get(path: '/content/review/manage/types', summary: '审核类型下拉', tags: ['审核管理'])]
    #[Permission(code: 'content:review:manage:index')]
    #[SimpleResponse(example: ['code' => 0, 'message' => 'success', 'data' => []])]
    public function types(Request $request): Response
    {
        try {
            return Json::success('ok', $this->service->getTypes());
        } catch (\Throwable $e) {
            return Json::fail($e->getMessage());
        }
    }

    /**
     * 审核统计
     */
    #[OA\Get(path: '/content/review/manage/statistics', summary: '审核统计', tags: ['审核管理'])]
    #[Permission(code: 'content:review:manage:index')]
    #[SimpleResponse(example: ['code' => 0, 'message' => 'success', 'data' => []])]
    public function statistics(Request $request): Response
    {
        try {
            return Json::success('ok', $this->service->getStatistics());
        } catch (\Throwable $e) {
            return Json::fail($e->getMessage());
        }
    }

    /**
     * 审批流进度（workflow 模式）
     */
    #[OA\Get(path: '/content/review/manage/{id}/flow-progress', summary: '审批流进度', tags: ['审核管理'])]
    #[Permission(code: 'content:review:manage:read')]
    #[SimpleResponse(example: ['code' => 0, 'message' => 'success', 'data' => []])]
    public function flowProgress(Request $request): Response
    {
        try {
            $id   = $request->route->param('id');
            $data = $this->service->getFlowProgress($id);
            return Json::success('ok', $data);
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
        if ($status = $request->input('status')) {
            $where['status'] = $status;
        }
        if ($flowType = $request->input('flow_type')) {
            $where['flow_type'] = $flowType;
        }
        if ($keyword = $request->input('keyword')) {
            $where['LIKE_title'] = $keyword;
        }
        return $where;
    }
}
