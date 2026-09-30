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

use app\adminapi\event\review\ReviewCreatedEvent;
use core\foundation\base\BaseListener;
use core\infrastructure\logger\Logger;

/**
 * 审核创建事件监听器
 */
class ReviewCreatedListener extends BaseListener
{
    protected function process($event): void
    {
        $review = $event->review;

        // 记录日志
        Logger::info('创建审核记录', [
            'review_id'        => $review->id,
            'reviewable_type'  => $review->reviewable_type,
            'reviewable_id'    => $review->reviewable_id,
        ]);
    }
}
