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

namespace app\adminapi\event\review;

use app\model\content\review\Review;
use core\foundation\base\BaseEvent;

/**
 * 审核创建事件
 */
class ReviewCreatedEvent extends BaseEvent
{
    public Review $review;

    public array $data;

    public function __construct(Review $review, array $data = [])
    {
        $this->review = $review;
        $this->data = $data;
    }

    public function getEventName(): string
    {
        return 'adminapi.review.created';
    }
}
