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

namespace core\interface\review;

use app\model\content\review\Review;

/**
 * 审批流网关接口（对接外部审批流引擎的预留对接点）
 *
 * 本阶段由 NullApprovalFlowGateway 占位（simple 模式本地闭环）。
 * 未来由审批流插件实现本接口，并通过 config('review.flow.gateway') 换装，
 * 即可无缝对接真实多级审批引擎；审批流引擎通过 onApproved/onRejected 回调回写。
 */
interface ApprovalFlowGateway
{
    /**
     * 发起审批流，返回实例ID并写入 review.flow_instance_id
     */
    public function start(Review $review, array $context = []): string;

    /**
     * 审批流回调：通过
     */
    public function onApproved(string $flowInstanceId, ?int $operatorId = null, string $comment = ''): void;

    /**
     * 审批流回调：拒绝
     */
    public function onRejected(string $flowInstanceId, string $comment = '', ?int $operatorId = null): void;

    /**
     * 查询审批流进度（供前端展示）
     */
    public function getProgress(string $flowInstanceId): array;
}
