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
 * Official Website: https://madong.tech
 */

namespace app\service\api\site;

use app\api\CurrentMember;
use app\dao\site\MenuDao;
use app\enum\common\EnabledStatus;
use core\foundation\base\BaseService;
use support\Container;

/**
 * 菜单服务
 */
class MenuService extends BaseService
{

    // 缓存键前缀
    public const CACHE_PREFIX = 'site_navigation_list';

    // 缓存过期时间（秒）
    public const CACHE_EXPIRE = 3600;

    public function __construct(MenuDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 获取导航菜单列表（带权限过滤）
     *
     * @return array
     * @throws \Exception
     */
    public function getNavigationList(): array
    {
        $map   = [
            ['enabled', 'eq', EnabledStatus::ENABLED->value],
            ['is_show', 'eq', 1],
        ];
        $menus = $this->dao->selectList($map, ['*'], 0, 0, 'sort asc, id asc');
        $menus = $menus->toArray();

        // 获取当前用户权限码
        $userPermissions = $this->getUserPermissions();
        $isLogin         = !empty($userPermissions);

        // 根据权限过滤菜单
        // 逻辑：
        //   is_public=1              → 公开
        //   is_public=0 + is_no_auth=1 → 仅需登录，跳过权限码校验
        //   is_public=0 + is_no_auth=0 → 需登录 + 有权限码时校验权限
        $filteredMenus = [];
        foreach ($menus as $menu) {
            // is_public=1（或非 0/false）→ 公开菜单，所有用户可见
            $isPublic = !empty($menu['is_public']) && $menu['is_public'] != 0;
            if ($isPublic) {
                $filteredMenus[] = $menu;
                continue;
            }
            // 非公开菜单 → 必须登录
            if (!$isLogin) {
                continue;
            }
            // is_no_auth=1 → 已登录即可，跳过权限码校验
            $isNoAuth = !empty($menu['is_no_auth']) && $menu['is_no_auth'] != 0;
            if ($isNoAuth) {
                $filteredMenus[] = $menu;
                continue;
            }
            $code = $menu['code'] ?? '';
            // code 为空 → 已登录即可访问
            if (empty($code)) {
                $filteredMenus[] = $menu;
                continue;
            }
            // code 非空 → 需在用户权限列表中（或用户有 * 超级权限）
            if (in_array('*', $userPermissions, true) || in_array($code, $userPermissions, true)) {
                $filteredMenus[] = $menu;
            }
        }

        // 解析 extra 扩展字段
        return $this->parseExtra($filteredMenus);
    }

    /**
     * 获取当前用户的权限码列表
     *
     * @return array
     */
    private function getUserPermissions(): array
    {
        try {
            /** @var CurrentMember $currentMember */
            $currentMember = Container::make(CurrentMember::class);
            return $currentMember->getPermissions();
        } catch (\Exception) {
            // 未登录或获取权限失败，返回空数组，只显示公开菜单
            return [];
        }
    }

    /**
     * 解析 extra 扩展字段
     * 将 JSON 字符串转换为数组并合并到菜单数据
     *
     * @param array $menus
     * @return array
     */
    protected function parseExtra(array $menus): array
    {
        return array_map(function ($menu) {
            // 解析 extra JSON
            if (!empty($menu['extra']) && is_string($menu['extra'])) {
                $extra = json_decode($menu['extra'], true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $menu['extra'] = $extra;
                } else {
                    $menu['extra'] = [];
                }
            } elseif (empty($menu['extra'])) {
                $menu['extra'] = [];
            }

            // 将常用字段映射到 extra（便于前端使用）
            $menu['extra'] = array_merge([
                'type' => $menu['type'] ?? 1,
                'target' => $menu['target'] ?? 1,
            ], $menu['extra']);

            return $menu;
        }, $menus);
    }

    /**
     * 清除菜单缓存
     *
     * @return void
     */
    public function clearMenuCache(): void
    {
        $this->cacheDriver()->delete(self::CACHE_PREFIX . '_all');
    }
}
