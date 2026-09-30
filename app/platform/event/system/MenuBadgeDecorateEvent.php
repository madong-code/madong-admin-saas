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
namespace app\platform\event\system;

use core\communication\notify\MenuBadgeDecorateEvent as CoreMenuBadgeDecorateEvent;

/**
 * 平台运营端菜单徽标装饰事件
 *
 * 业务监听器挂载在 'platform.menu.badge_decorate' 上，为菜单追加/覆盖徽标。
 *
 * @see \core\communication\notify\MenuBadgeDecorateEvent
 */
class MenuBadgeDecorateEvent extends CoreMenuBadgeDecorateEvent
{
    public function getEventName(): string
    {
        return 'platform.menu.badge_decorate';
    }
}