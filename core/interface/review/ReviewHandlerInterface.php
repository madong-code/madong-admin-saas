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
 * 审核处理器契约
 *
 * 每个审核类型（type）在 review.php 中通过 `handler` 声明实现类，
 * 由 ReviewService 在审核通过/拒绝/取消时回调，负责更新业务表状态。
 *
 * 与事件监听器（listener）职责解耦：
 * - Handler：更新业务数据（如把评论状态改为已通过）
 * - Listener：发通知等副作用
 */
interface ReviewHandlerInterface
{
    /**
     * 审核通过：更新业务表状态
     */
    public function onApproved(Review $review): void;

    /**
     * 审核拒绝：更新业务表状态
     */
    public function onRejected(Review $review): void;

    /**
     * 审核取消：回滚业务表状态
     */
    public function onCanceled(Review $review): void;
}
