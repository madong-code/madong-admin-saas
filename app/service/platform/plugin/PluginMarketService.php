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

namespace app\service\platform\plugin;

use app\service\core\plugin\PluginService;
use core\foundation\base\BaseService;
use support\Container;

/**
 * 平台端模块市场服务 - 委托 core PluginService
 */
class PluginMarketService extends BaseService
{
    private PluginService $pluginService;

    public function __construct()
    {
        $this->pluginService = Container::make(PluginService::class);
    }

    public function getList(int $page, int $limit, string $keyword = ''): array
    {
        return $this->pluginService->getList($page, $limit, 'all', $keyword);
    }
}
