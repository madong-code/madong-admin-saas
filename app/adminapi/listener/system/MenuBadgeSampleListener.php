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
namespace app\adminapi\listener\system;

use app\service\admin\content\message\NotifyService;
use core\communication\notify\MenuBadgeDecorateEvent;
use core\foundation\base\BaseListener;
use core\infrastructure\logger\Logger;
use support\Container;

/**
 * 菜单徽标示例监听器（验收用）
 *
 * 按当前用户的站内信未读总数，为 /content/message/notify 菜单设置文字徽标，
 * 其父级自动冒泡为圆点徽标。
 *
 * 业务方可将此类替换/扩展为实际统计逻辑（待办、审批、工单等）。
 */
class MenuBadgeSampleListener extends BaseListener
{
    protected function process($event): void
    {
        if (!$event instanceof MenuBadgeDecorateEvent) {
            return;
        }

        // 徽标属于增强信息，统计失败不得影响菜单接口返回
        try {
            $unread = (int)(Container::make(NotifyService::class)->getUnreadCount((string)$event->userId)['total'] ?? 0);
            if ($unread > 0) {
                $event->setBadgeWithParents('/content/message/notify', (string)$unread, 'primary');
            }
        } catch (\Throwable $e) {
            Logger::error('菜单徽标示例监听器统计失败: ' . $e->getMessage(), [
                'user_id' => $event->userId,
            ]);
        }
    }
}