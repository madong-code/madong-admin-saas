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
use app\model\content\review\Review;
use support\Log;

/**
 * 默认审批流网关（占位实现）
 *
 * - simple 模式：不接入外部审批流，本地直接审核闭环，无需网关参与。
 * - workflow 模式：未安装真实审批流引擎插件时，start() 仅记录日志并返回空实例ID，
 *   审核记录保持 PROCESSING 状态等待引擎回调；此时仅超级管理员可超审批。
 *
 * 未来由审批流插件实现 ApprovalFlowGateway，并通过 config('review.flow.gateway') 换装，
 * 即可无缝对接真实审批流引擎。
 */
class NullApprovalFlowGateway implements ApprovalFlowGateway
{
    public function start(Review $review, array $context = []): string
    {
        Log::info('[Review] 未配置外部审批流网关，使用本地闭环占位', ['review_id' => $review->id]);
        return '';
    }

    public function onApproved(string $flowInstanceId, ?int $operatorId = null, string $comment = ''): void
    {
        // 无实体引擎，无需处理。
    }

    public function onRejected(string $flowInstanceId, string $comment = '', ?int $operatorId = null): void
    {
        // 无实体引擎，无需处理。
    }

    public function getProgress(string $flowInstanceId): array
    {
        return [];
    }
}
