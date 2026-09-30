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
namespace app\service\api\site;

use app\service\api\system\ConfigService;
use core\foundation\base\BaseService;

/**
 * 站点数据服务
 */
class SiteService extends BaseService
{
    private AdvertisementService $advertisementService;
    private LinkService $linkService;
    private MenuService $menuService;
    private ConfigService $configService;

    public function __construct(
        AdvertisementService $advertisementService,
        LinkService $linkService,
        MenuService $menuService,
        ConfigService $configService
    ) {
        $this->advertisementService = $advertisementService;
        $this->linkService = $linkService;
        $this->menuService = $menuService;
        $this->configService = $configService;
    }

    /**
     * 获取站点首页数据
     */
    public function getSiteData(): array
    {
        return [
            'advertisements' => $this->advertisementService->getAds(),
            'links'          => $this->linkService->getAllLinks(),
            'menus'          => $this->menuService->getNavigationList(),
            'config'         => $this->configService->getByGroup('web'),
        ];
    }

    /**
     * 获取路由菜单模式配置
     * - routing_mode: 运行期路由模式（frontend/backend/hybrid），覆盖构建期默认值
     * - menus: backend/hybrid 模式下的后端菜单数据（按 category 分组为 nav + member）
     * - menu_visibility: 前端模式下按 path 下发菜单可见性与权限
     * - max_nav_items: PC 导航栏固定显示数量
     */
    public function getRoutingConfig(): array
    {
        $routingMode    = $this->configService->config('routing_mode', '', ['group_code' => 'web']);
        $menuVisibility = $this->configService->config('menu_visibility', [], ['group_code' => 'web']);
        $maxNavItems    = $this->configService->config('max_nav_items', 0, ['group_code' => 'web']);

        $result = [];
        if (!empty($routingMode) && in_array($routingMode, ['frontend', 'backend', 'hybrid'], true)) {
            $result['routing_mode'] = $routingMode;
        }
        if (!empty($menuVisibility) && is_array($menuVisibility)) {
            $result['menu_visibility'] = $menuVisibility;
        }
        if (!empty($maxNavItems) && (int)$maxNavItems > 0) {
            $result['max_nav_items'] = (int)$maxNavItems;
        }

        // 始终返回后端定义的菜单，前端按 category 分组使用
        try {
            $result['menus'] = $this->menuService->getNavigationList();
        } catch (\Exception $e) {
            $result['menus'] = [];
        }

        return $result;
    }
}
