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

namespace app\adminapi\listener\review;

use app\adminapi\event\review\ReviewRejectedEvent;
use core\foundation\base\BaseListener;
use core\infrastructure\logger\Logger;

/**
 * 审核拒绝事件监听器
 *
 * 说明：审核状态流转与业务表更新由 ReviewService 统一编排（handler 更新业务状态、服务写归档），
 * 本监听器仅作审计日志与通知等副作用，不再改写 review 模型（避免与归档软删冲突）。
 */
class ReviewRejectedListener extends BaseListener
{
    protected function process($event): void
    {
        $review = $event->review;
        $reason = $event->data['reason'] ?? '';

        Logger::info('审核拒绝', [
            'review_id'        => $review->id,
            'reviewable_type'  => $review->reviewable_type,
            'reviewable_id'    => $review->reviewable_id,
            'reviewer_id'      => $review->reviewer_id,
            'reason'           => $reason,
        ]);
    }
}
