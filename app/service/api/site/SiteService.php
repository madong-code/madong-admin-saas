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

use app\event\site\SiteNavConfigEvent;
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
     * 获取站点导航配置（登录态无关，整体输出）
     * - routing_mode: 运行期路由模式（frontend/backend/hybrid），覆盖构建期默认值
     * - menu_visibility: 前端模式下按 path 下发菜单可见性与权限
     * - max_nav_items: PC 导航栏固定显示数量
     * - codes: 全部菜单权限码集合（backend/hybrid 模式校验非公开路由有效性）
     * - menus/member_menus/header_actions/footer_menus: category=1/2/3/4 全量树，
     *   节点带 is_public（显隐语义，非权限语义）/is_no_auth/code，由前端按登录态过滤
     */
    public function getNavConfig(): array
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

        try {
            $all = $this->menuService->getNavigationAllList();
        } catch (\Exception) {
            $all = [];
        }

        $groups = ['1' => [], '2' => [], '3' => [], '4' => []];
        $codes  = [];
        foreach ($all as $menu) {
            $category = (string)($menu['category'] ?? '');
            if (!isset($groups[$category])) {
                continue;
            }
            $groups[$category][] = $menu;
            $code = (string)($menu['code'] ?? '');
            if ($code !== '' && !in_array($code, $codes, true)) {
                $codes[] = $code;
            }
        }

        $result['codes']          = $codes;
        $result['menus']          = $this->buildNavTree($groups['1'], '0');
        $result['member_menus']   = $this->buildNavTree($groups['2'], '0');
        $result['header_actions'] = $this->buildNavTree($groups['3'], '0');
        $result['footer_menus']   = $this->buildNavTree($groups['4'], '0');

        // 插件注入/改写导航节点（portal：系统栏目 active_prefix 正身/详情域联动高亮）
        $event = new SiteNavConfigEvent($result['menus'], $result['member_menus']);
        $event->dispatch();
        $result['menus']        = $event->menus;
        $result['member_menus'] = $event->memberMenus;

        return $result;
    }

    /**
     * web_menu 平铺行 → 导航树（pid 组树 + external/active_prefix/icon/is_public 映射）
     * active_prefix 默认降级为 [url] 自身前缀，插件监听器可按系统栏目改写
     */
    private function buildNavTree(array $rows, string $pid): array
    {
        $out = [];
        foreach ($rows as $row) {
            if ((string)($row['pid'] ?? '0') !== $pid) {
                continue;
            }
            $url      = (string)($row['url'] ?? '');
            $external = (int)($row['target'] ?? 1) === 2 || (bool)preg_match('#^https?://#i', $url);
            $out[]    = [
                'name'          => (string)($row['name'] ?? ''),
                'url'           => $url,
                'external'      => $external,
                'active_prefix' => ($external || $url === '') ? [] : [$url],
                'icon'          => (string)($row['icon'] ?? ''),
                'is_public'     => !empty($row['is_public']) && $row['is_public'] != 0,
                'is_no_auth'    => !empty($row['is_no_auth']) && $row['is_no_auth'] != 0,
                'code'          => (string)($row['code'] ?? ''),
                'children'      => $this->buildNavTree($rows, (string)($row['id'] ?? '0')),
            ];
        }
        return $out;
    }
}
