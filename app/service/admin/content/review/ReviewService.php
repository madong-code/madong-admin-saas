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

namespace app\service\admin\content\review;

use core\interface\review\ApprovalFlowGateway;
use core\interface\review\ReviewHandlerInterface;
use app\dao\content\review\ReviewArchiveDao;
use app\dao\content\review\ReviewDao;
use app\dao\content\review\ReviewLogDao;
use app\enum\review\ReviewStatus;
use app\adminapi\event\review\ReviewApprovedEvent;
use app\adminapi\event\review\ReviewCanceledEvent;
use app\adminapi\event\review\ReviewCreatedEvent;
use app\adminapi\CurrentUser;
use app\adminapi\event\review\ReviewRejectedEvent;
use app\exception\ReviewFlowLockedException;
use app\model\content\review\Review;
use core\foundation\base\BaseService;
use Illuminate\Support\Collection;
use support\Container;
use support\Log;

/**
 * 审核服务（内核）
 *
 * 职责：
 * - 创建审核记录（按 全局开关 + 类型覆盖 决定 simple / workflow 模式）
 * - 通过 / 拒绝 / 取消（含外部审批流锁定拦截 + 超审批）
 * - 创建即双表同建（运行表 sys_review 与记录表 sys_review_archive 共用同一雪花ID），运行期实时同步归档行，终结就地更新（双表物理分离）
 * - 派发事件 + 回调业务处理器（handler）+ 记录操作日志
 * - 委托审批流网关（ApprovalFlowGateway）
 */
class ReviewService extends BaseService
{
    public function __construct(ReviewDao $dao)
    {
        $this->dao = $dao;
    }

    /* ===================== 创建 ===================== */

    /**
     * 创建审核记录
     *
     * @param string $type 审核类型键（如 comment）
     * @param int|string $reviewableId 业务对象ID
     * @param array $options [reason, extra_data, flow_context]
     * @throws \RuntimeException
     */
    public function createReview(string $type, int|string $reviewableId, array $options = []): Review
    {
        if ($this->dao->getByReviewable($type, $reviewableId)) {
            throw new \RuntimeException('该记录已存在审核记录');
        }

        $flowType = $this->resolveFlowType($type);
        $data = [
            'reviewable_type' => $type,
            'reviewable_id'   => $reviewableId,
            'status'          => $flowType === 'workflow' ? ReviewStatus::PROCESSING->value : ReviewStatus::PENDING->value,
            'flow_type'       => $flowType,
        ];
        if (!empty($options['reason'])) {
            $data['reason'] = $options['reason'];
        }
        // 固化业务表单数据快照：优先从业务模型读取，调用方显式传入的 extra_data 优先。
        // 使审核记录在展示/审批时自包含，不依赖关联业务表（避免多态 N 表的 join/引用完整性问题）。
        $extraData = ReviewFieldMapper::captureSnapshot($type, $reviewableId, $options['extra_data'] ?? []);
        if (!empty($extraData)) {
            $data['extra_data'] = $extraData;
        }

        // 创建即双表同建：运行表与记录表共用同一雪花ID，并在同一事务内完成，杜绝孤儿归档行。
        /** @var Review $review */
        $review = $this->transaction(function () use ($data) {
            $review = $this->dao->save($data);
            $this->createArchiveRecord($review);
            return $review;
        });

        if ($flowType === 'workflow') {
            $instanceId = $this->resolveGateway()->start($review, $options['flow_context'] ?? []);
            if ($instanceId !== '') {
                $review->flow_instance_id = $instanceId;
                $review->save();
                // 运行期实时同步：外部流实例ID 写回归档行，归档行始终为最新态。
                $this->syncArchiveFlowInstance($review, $instanceId);
            }
        }

        (new ReviewCreatedEvent($review))->dispatch();
        return $review;
    }

    /* ===================== 审核动作 ===================== */

    /**
     * 通过
     *
     * @param array $options [operator_id, reason, force]
     * @throws ReviewFlowLockedException|\RuntimeException
     */
    public function approve(int|string $id, array $options = []): bool
    {
        $force = $this->resolveForce($options);
        $operatorId = $this->resolveOperatorId($options);
        $reason = $options['reason'] ?? '';

        return $this->transaction(function () use ($id, $force, $operatorId, $reason) {
            /** @var Review $review */
            $review = $this->dao->get($id);
            if (!$review) {
                throw new \RuntimeException('审核记录不存在');
            }
            if ($review->isTerminated()) {
                throw new \RuntimeException('该记录已处理');
            }
            if ($review->isWorkflowLocked() && !$force) {
                throw new ReviewFlowLockedException();
            }

            $review->status = ReviewStatus::APPROVED->value;
            $review->reviewer_id = $operatorId;
            $review->reviewed_at = time();
            if ($reason !== '') {
                $review->reason = $reason;
            }

            $this->writeLog($review, 'approve', $operatorId, $reason);
            $this->dispatchHandler('onApproved', $review);
            $this->archiveReview($review);

            (new ReviewApprovedEvent($review))->dispatch();
            return true;
        });
    }

    /**
     * 拒绝
     *
     * @param array $options [operator_id, reason, force]
     * @throws ReviewFlowLockedException|\RuntimeException
     */
    public function reject(int|string $id, array $options = []): bool
    {
        $force = $this->resolveForce($options);
        $operatorId = $this->resolveOperatorId($options);
        $reason = $options['reason'] ?? '';
        if ($reason === '') {
            throw new \RuntimeException('拒绝原因不能为空');
        }

        return $this->transaction(function () use ($id, $force, $operatorId, $reason) {
            /** @var Review $review */
            $review = $this->dao->get($id);
            if (!$review) {
                throw new \RuntimeException('审核记录不存在');
            }
            if ($review->isTerminated()) {
                throw new \RuntimeException('该记录已处理');
            }
            if ($review->isWorkflowLocked() && !$force) {
                throw new ReviewFlowLockedException();
            }

            $review->status = ReviewStatus::REJECTED->value;
            $review->reviewer_id = $operatorId;
            $review->reviewed_at = time();
            $review->reason = $reason;

            $this->writeLog($review, 'reject', $operatorId, $reason);
            $this->dispatchHandler('onRejected', $review);
            $this->archiveReview($review);

            (new ReviewRejectedEvent($review))->dispatch();
            return true;
        });
    }

    /**
     * 取消
     *
     * @param array $options [operator_id, reason]
     */
    public function cancel(int|string $id, array $options = []): bool
    {
        $operatorId = $this->resolveOperatorId($options);
        $reason = $options['reason'] ?? '';

        return $this->transaction(function () use ($id, $operatorId, $reason) {
            /** @var Review $review */
            $review = $this->dao->get($id);
            if (!$review) {
                throw new \RuntimeException('审核记录不存在');
            }
            if ($review->isTerminated()) {
                throw new \RuntimeException('该记录已处理');
            }

            $review->status = ReviewStatus::CANCELED->value;
            $review->reviewer_id = $operatorId;
            $review->reviewed_at = time();
            $review->cancel_reason = $reason;

            $this->writeLog($review, 'cancel', $operatorId, $reason);
            $this->dispatchHandler('onCanceled', $review);
            $this->archiveReview($review);

            (new ReviewCanceledEvent($review))->dispatch();
            return true;
        });
    }

    /* ===================== 批量 ===================== */

    public function batchApprove(array $ids, array $options = []): int
    {
        $count = 0;
        $firstError = null;
        foreach ($ids as $id) {
            try {
                if ($this->approve($id, $options)) {
                    $count++;
                }
            } catch (\Throwable $e) {
                $firstError = $firstError ?? $e->getMessage();
                Log::warning('批量审核通过失败: ' . $e->getMessage(), ['id' => $id]);
            }
        }
        // 全部失败时抛出首个错误，避免静默返回成功（前端据此提示真实原因）
        if ($count === 0 && $firstError !== null) {
            throw new \RuntimeException($firstError);
        }
        return $count;
    }

    public function batchReject(array $ids, array $options = []): int
    {
        $count = 0;
        $firstError = null;
        foreach ($ids as $id) {
            try {
                if ($this->reject($id, $options)) {
                    $count++;
                }
            } catch (\Throwable $e) {
                $firstError = $firstError ?? $e->getMessage();
                Log::warning('批量审核拒绝失败: ' . $e->getMessage(), ['id' => $id]);
            }
        }
        // 全部失败时抛出首个错误，避免静默返回成功（前端据此提示真实原因）
        if ($count === 0 && $firstError !== null) {
            throw new \RuntimeException($firstError);
        }
        return $count;
    }

    /* ===================== 查询 ===================== */

    /**
     * 运行中列表（仅 sys_review 热数据），并映射字段
     */
    public function getMappedList(array $where, $field = '*', int $page = 1, int $limit = 20, string $order = ''): Collection
    {
        $list = $this->dao->selectList($where, $field, $page, $limit, $order);
        return ReviewFieldMapper::mapReviews($list ?? collect());
    }

    /**
     * 归档列表（sys_review_archive 冷数据）
     */
    public function getArchiveList(array $where, $field = '*', int $page = 1, int $limit = 20, string $order = ''): Collection
    {
        /** @var ReviewArchiveDao $archiveDao */
        $archiveDao = Container::make(ReviewArchiveDao::class);
        // 仅检索已终结（archived_at 非空）的归档行，排除创建即写入、仍运行中的记录，避免污染归档视图/统计。
        $list = $archiveDao->selectFinishedList($where, $field, $page, $limit, $order);
        return ReviewFieldMapper::mapReviews($list ?? collect());
    }

    public function getCount(array $where): int
    {
        return $this->dao->count($where);
    }

    public function getArchiveCount(array $where): int
    {
        /** @var ReviewArchiveDao $archiveDao */
        $archiveDao = Container::make(ReviewArchiveDao::class);
        return $archiveDao->countFinished($where);
    }

    public function getDetail(int|string $id): array
    {
        /** @var Review|null $review */
        $review = $this->dao->get($id);
        if (!$review) {
            return [];
        }
        return ReviewFieldMapper::mapReview($review);
    }

    public function getArchiveDetail(int|string $id): array
    {
        /** @var ReviewArchiveDao $archiveDao */
        $archiveDao = Container::make(ReviewArchiveDao::class);
        $review = $archiveDao->get($id);
        if (!$review) {
            return [];
        }
        return ReviewFieldMapper::mapReview($review);
    }

    /**
     * 审核详情（聚合视图）
     *
     * 返回四个维度，供前端详情页一站式渲染：
     * - review_info 审核信息（基础字段映射，含类型/申请人/状态文案等）
     * - form        业务表单快照（extra_data，自包含，不依赖业务表）
     * - status      当前状态信息（状态/审批模式/审核人/原因等）
     * - events      操作事件轨迹（sys_review_log，按时间正序）
     *
     * 运行中记录优先取 sys_review；已终结（被归档、运行表软删）则回退 sys_review_archive，
     * 二者主键一致，事件日志按同一 review_id 关联，无需区分。
     *
     * @return array{is_archived:bool, review_info:array, form:array, status:array, events:array}
     */
    public function getReviewDetail(int|string $id): array
    {
        /** @var Review|null $review */
        $review = $this->dao->get($id);
        $fromArchive = false;
        if (!$review) {
            /** @var ReviewArchiveDao $archiveDao */
            $archiveDao = Container::make(ReviewArchiveDao::class);
            $review = $archiveDao->get($id);
            $fromArchive = true;
        }
        if (!$review) {
            return [];
        }

        // 审核信息
        $reviewInfo = ReviewFieldMapper::mapReview($review);

        // 表单：业务数据快照（自包含，展示不依赖业务表）
        $form = $review->extra_data ?? [];

        // 状态信息
        $status = [
            'status'           => $review->status,
            'status_text'      => ReviewStatus::fromValue($review->status)->label(),
            'flow_type'        => $review->flow_type,
            'flow_instance_id' => $review->flow_instance_id,
            'reviewer_id'      => $review->reviewer_id ?? null,
            'reviewed_at'      => $review->reviewed_at ?? null,
            'reason'           => $review->reason ?? null,
            'cancel_reason'    => $review->cancel_reason ?? null,
        ];

        // 事件：操作审计轨迹（时间正序）
        /** @var ReviewLogDao $logDao */
        $logDao = Container::make(ReviewLogDao::class);
        $logs = $logDao->selectList(['review_id' => $id], '*', 0, 0, 'created_at asc');
        $events = [];
        foreach ($logs ?? [] as $log) {
            $events[] = [
                'id'          => $log->id,
                'action'      => $log->action,
                'action_text' => $this->getActionLabel($log->action),
                'operator_id' => $log->operator_id ?? null,
                'reason'      => $log->reason ?? null,
                'created_at'  => $log->created_at ?? null,
                'created_by'  => $log->created_by ?? null,
            ];
        }

        return [
            'is_archived' => $fromArchive,
            'review_info' => $reviewInfo,
            'form'        => $form,
            'status'      => $status,
            'events'      => $events,
        ];
    }

    /**
     * 审核动作中文文案（日志 action 字段：approve|reject|cancel 等）
     */
    protected function getActionLabel(?string $action): string
    {
        return match ($action) {
            'approve' => '通过',
            'reject'  => '拒绝',
            'cancel'  => '取消',
            'submit'  => '发起',
            default   => $action ?? '未知',
        };
    }

    /**
     * 按类型导航到关联业务表（只读），用于"查看线上最新状态/跳转"。
     *
     * 审核展示不依赖此方法（展示以 extra_data 快照为准）；它仅在需要回看业务侧当前态、
     * 或终审回写前读取最新值时使用。类型 → 业务模型的解析统一收敛到 ReviewFieldMapper，
     * 避免散落的多态关联查询。
     */
    public function getLiveRecord(int|string $id): ?array
    {
        /** @var Review|null $review */
        $review = $this->dao->get($id);
        if (!$review) {
            return null;
        }
        $modelClass = ReviewFieldMapper::getModelClass($review->reviewable_type);
        if (!$modelClass) {
            return null;
        }
        try {
            $business = $modelClass::find($review->reviewable_id);
        } catch (\Throwable $e) {
            return null;
        }
        return $business ? $business->toArray() : null;
    }

    /**
     * 审核类型下拉
     */
    public function getTypes(): array
    {
        return ReviewFieldMapper::getTypes();
    }

    /**
     * 统计（运行中 + 归档）
     */
    public function getStatistics(): array
    {
        return [
            'pending'    => $this->dao->count(['status' => ReviewStatus::PENDING->value]),
            'processing' => $this->dao->count(['status' => ReviewStatus::PROCESSING->value]),
            'archived'   => $this->getArchiveCount([]),
        ];
    }

    /**
     * 获取审批流进度（供前端展示）
     */
    public function getFlowProgress(int|string $id): array
    {
        /** @var Review|null $review */
        $review = $this->dao->get($id);
        if (!$review || empty($review->flow_instance_id)) {
            return [];
        }
        return $this->resolveGateway()->getProgress($review->flow_instance_id);
    }

    /* ===================== 内部方法 ===================== */

    /**
     * 解析审核模式：全局开关 + 类型级覆盖
     */
    protected function resolveFlowType(string $type): string
    {
        $globalEnabled = (bool)config('review.flow.enabled', false);
        $typeConfig = ReviewFieldMapper::getTypeConfig($type) ?? [];
        $flowConfig = $typeConfig['flow'] ?? null;
        if (is_array($flowConfig) && array_key_exists('enabled', $flowConfig)) {
            return !empty($flowConfig['enabled']) ? 'workflow' : 'simple';
        }
        return $globalEnabled ? 'workflow' : 'simple';
    }

    /**
     * 解析审批流网关
     */
    protected function resolveGateway(): ApprovalFlowGateway
    {
        $gateway = config('review.flow.gateway', NullApprovalFlowGateway::class);
        if (is_string($gateway)) {
            $gateway = Container::make($gateway);
        }
        return $gateway;
    }

    /**
     * 是否强制（超审批）：显式 force 或当前为超级管理员
     */
    protected function resolveForce(array $options): bool
    {
        if (!empty($options['force'])) {
            return true;
        }
        return $this->isSuperAdmin();
    }

    /**
     * 解析操作人ID
     */
    protected function resolveOperatorId(array $options): int
    {
        if (!empty($options['operator_id'])) {
            return (int)$options['operator_id'];
        }
        return $this->currentUserId();
    }

    /**
     * 当前登录管理员ID
     */
    protected function currentUserId(): int
    {
        try {
            $cu = Container::make(CurrentUser::class);
            return $cu ? (int)$cu->id() : 0;
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /**
     * 当前是否超级管理员
     */
    protected function isSuperAdmin(): bool
    {
        try {
            $cu = Container::make(CurrentUser::class);
            return $cu ? (bool)$cu->isSuperAdmin() : false;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * 写操作日志
     */
    protected function writeLog(Review $review, string $action, int $operatorId, string $reason): void
    {
        /** @var ReviewLogDao $logDao */
        $logDao = Container::make(ReviewLogDao::class);
        $logDao->save([
            'review_id'   => $review->id,
            'action'      => $action,
            'operator_id' => $operatorId,
            'reason'      => $reason,
            'created_at'  => time(),
            'updated_at'  => time(),
        ]);
    }

    /**
     * 回调业务处理器（更新业务表状态），失败不影响审核主流程
     */
    protected function dispatchHandler(string $method, Review $review): void
    {
        try {
            $handlerClass = ReviewFieldMapper::getHandlerClass($review->reviewable_type);
            if (empty($handlerClass) || !class_exists($handlerClass)) {
                return;
            }
            $handler = is_string($handlerClass) ? Container::make($handlerClass) : $handlerClass;
            if ($handler instanceof ReviewHandlerInterface && method_exists($handler, $method)) {
                $handler->{$method}($review);
            }
        } catch (\Throwable $e) {
            Log::error('审核处理器执行失败: ' . $e->getMessage(), [
                'review_id' => $review->id,
                'method'    => $method,
            ]);
        }
    }

    /**
     * 实时归档：复制至归档表并软删运行表（同事务内）
     */
    protected function archiveReview(Review $review): void
    {
        // 归档行已于创建期建立（共用同一ID）；终结算改为就地更新该归档行（写入终态与 archived_at），
        // 不再整行拷贝，使归档实时对接业务表。
        $attributes = $this->buildArchiveAttributes($review, time());

        /** @var ReviewArchiveDao $archiveDao */
        $archiveDao = Container::make(ReviewArchiveDao::class);
        $existing = $archiveDao->get($review->id);
        if ($existing) {
            $existing->fill($attributes);
            $existing->save();
        } else {
            // 兜底：存量或异常数据缺失归档行时插入（保持历史拷贝语义）。
            $archiveDao->save($attributes);
        }
        $review->delete();
    }

    /* ===================== 归档行构建/同步（双表同建） ===================== */

    /**
     * 归档 DAO 实例
     */
    private function archiveDao(): ReviewArchiveDao
    {
        /** @var ReviewArchiveDao $archiveDao */
        $archiveDao = Container::make(ReviewArchiveDao::class);
        return $archiveDao;
    }

    /**
     * 由运行行构建归档行属性（共用ID，必要时写入 archived_at）
     */
    private function buildArchiveAttributes(Review $review, ?int $archivedAt = null): array
    {
        $attributes = $review->getAttributes();
        // getAttributes() 返回的 extra_data 是 JSON 原始字符串；归档表该列同为 array 类型，
        // 需先解码为数组，避免二次 json_encode 损坏数据。
        if (isset($attributes['extra_data']) && is_string($attributes['extra_data'])) {
            $decoded = json_decode($attributes['extra_data'], true);
            if (is_array($decoded)) {
                $attributes['extra_data'] = $decoded;
            }
        }
        if ($archivedAt !== null) {
            $attributes['archived_at'] = $archivedAt;
        }
        return $attributes;
    }

    /**
     * 创建即双表同建：以同一ID在记录表建立归档行（初始态、archived_at 为空）
     */
    private function createArchiveRecord(Review $review): void
    {
        $this->archiveDao()->save($this->buildArchiveAttributes($review));
    }

    /**
     * 运行期实时同步：将外部流实例ID 写回归档行，使其始终为最新态
     */
    private function syncArchiveFlowInstance(Review $review, string $instanceId): void
    {
        $archive = $this->archiveDao()->get($review->id);
        if ($archive) {
            $archive->flow_instance_id = $instanceId;
            $archive->save();
        }
    }
}
