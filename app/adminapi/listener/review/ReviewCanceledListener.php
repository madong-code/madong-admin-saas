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

use app\adminapi\event\review\ReviewCanceledEvent;
use core\foundation\base\BaseListener;
use core\infrastructure\logger\Logger;

/**
 * 审核取消事件监听器
 */
class ReviewCanceledListener extends BaseListener
{
    protected function process($event): void
    {
        $review = $event->review;
        $reason = $event->data['reason'] ?? '';

        Logger::info('审核取消', [
            'review_id'        => $review->id,
            'reviewable_type'  => $review->reviewable_type,
            'reviewable_id'    => $review->reviewable_id,
            'operator_id'      => $review->reviewer_id,
            'reason'           => $reason,
        ]);
    }
}
