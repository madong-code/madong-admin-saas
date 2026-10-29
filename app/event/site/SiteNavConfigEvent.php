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
namespace app\event\site;

use core\foundation\base\BaseEvent;

/**
 * 站点导航配置组装事件（/site/nav-config）
 *
 * 数据载体：监听器可直接改写 $menus / $memberMenus（如 portal 插件
 * 注入系统栏目 active_prefix 高亮前缀；未装插件时框架降级为 [url] 自身前缀）。
 */
class SiteNavConfigEvent extends BaseEvent
{
    public function __construct(
        public array $menus,
        public array $memberMenus,
    ) {
    }

    public function getEventName(): string
    {
        return 'site.nav_config';
    }
}
