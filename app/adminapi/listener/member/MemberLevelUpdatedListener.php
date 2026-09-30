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
namespace app\adminapi\listener\member;

use app\adminapi\event\member\MemberLevelUpdatedEvent;
use core\foundation\base\BaseListener;

/**
 * 会员等级更新监听器
 *
 * 处理会员等级更新的事件：
 * 1. 发送等级变更通知
 * 2. 记录等级变更日志
 */
class MemberLevelUpdatedListener extends BaseListener
{
    protected function process($event): void
    {
        // 其他等级更新相关的处理
        $this->handleLevelChange($event);
    }

    /**
     * 处理等级变更
     */
    private function handleLevelChange(MemberLevelUpdatedEvent $event): void
    {
        // 这里可以添加等级变更后的处理逻辑
        // 例如：
        // 1. 给会员发送等级提升的奖励
        // 2. 更新会员的特权
        // 3. 记录等级变更的历史

        // 示例：如果是等级提升，可以发送通知
        if ($event->oldLevelId && $event->newLevelId) {
            // 等级变更的处理
            echo "会员 {$event->memberId} 等级从 {$event->oldLevelName} 变更为 {$event->newLevelName}\n";
        } elseif (!$event->oldLevelId && $event->newLevelId) {
            // 首次获得等级的处理
            echo "会员 {$event->memberId} 获得等级 {$event->newLevelName}\n";
        } elseif ($event->oldLevelId && !$event->newLevelId) {
            // 失去等级的处理
            echo "会员 {$event->memberId} 失去等级 {$event->oldLevelName}\n";
        }
    }
}
