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
        // 登录判定以会员身份为准：普通会员可能无任何标签权限码，
        // 仅凭权限码非空判定会把已登录普通会员误判为游客（导致 is_public=0 菜单全部不可见）
        $isLogin = $this->isMemberLogin();

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
     * 获取导航菜单全量列表（登录态无关，不做权限/登录过滤）
     *
     * 供 /site/nav-config 整体输出：is_public/is_no_auth/code 作为节点字段随树下发，
     * 显隐由前端按登录态响应式过滤（页面保护由路由 auth 中间件负责）。
     */
    public function getNavigationAllList(): array
    {
        $map   = [
            ['enabled', 'eq', EnabledStatus::ENABLED->value],
            ['is_show', 'eq', 1],
        ];
        $menus = $this->dao->selectList($map, ['*'], 0, 0, 'sort asc, id asc');

        return $this->parseExtra($menus->toArray());
    }

    /**
     * 当前请求是否携带有效会员身份（token 可解析出会员 uid）
     */
    private function isMemberLogin(): bool
    {
        try {
            /** @var CurrentMember $currentMember */
            $currentMember = Container::make(CurrentMember::class);
            return !empty($currentMember->id());
        } catch (\Exception) {
            return false;
        }
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
