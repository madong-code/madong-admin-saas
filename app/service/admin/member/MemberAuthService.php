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

namespace app\service\admin\member;

use app\dao\web\MenuDao;
use app\dao\member\MemberDao;
use app\service\admin\web\MenuService as SiteMenuService;
use core\foundation\base\BaseService;
use Illuminate\Database\Eloquent\Collection;
use support\Container;

class MemberAuthService extends BaseService
{

    public function __construct(MemberDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 获取菜单
     *
     * @param string|int $memberId         会员ID
     * @param bool       $includeEmptyCode 是否包含权限码为空的菜单（不需要标签权限的公共菜单）
     *
     * @return \Illuminate\Database\Eloquent\Collection|null
     * @throws \Exception
     */
    public function getMenusByMemberTags(string|int $memberId, bool $includeEmptyCode = false): ?Collection
    {
        if (empty($memberId)) {
            return null;
        }

        $member = $this->dao->find($memberId);
        if (!$member) {
            return null;
        }

        $menuIds = [];
        $tags    = $member->tags()->where('enabled', 1)->get();

        foreach ($tags as $tag) {
            $permissions = $tag->permissions()->get();
            foreach ($permissions as $permission) {
                $menuIds[] = $permission->id;
            }
        }

        if (empty($menuIds) && !$includeEmptyCode) {
            return null;
        }

        $menuIds     = array_unique($menuIds);
        $menuService = new SiteMenuService(new MenuDao());
        $menus       = $this->getMenusByIds($menuService, $menuIds, $includeEmptyCode);

        return $menus->where('menu_type', '!=', 'button');
    }

    /**
     * 根据标签ID获取菜单
     *
     * @param string|int $tagId            标签ID
     * @param bool       $includeEmptyCode 是否包含权限码为空的菜单（不需要标签权限的公共菜单）
     *
     * @return \Illuminate\Database\Eloquent\Collection|null
     * @throws \Exception
     */
    public function getMenusByTagId(string|int $tagId, bool $includeEmptyCode = false): ?Collection
    {
        if (empty($tagId)) {
            return null;
        }

        $menuIds = [];
        /** @var   MemberTagService $memberTagService */
        $memberTagService = Container::make(MemberTagService::class);
        $memberTag        = $memberTagService->get($tagId);
        if (!$memberTag) {
            return null;
        }

        $permissions = $memberTag->permissions()->get();
        foreach ($permissions as $permission) {
            $menuIds[] = $permission->id;
        }

        if (empty($menuIds) && !$includeEmptyCode) {
            return null;
        }

        $menuIds     = array_unique($menuIds);
        $menuService = new SiteMenuService(new MenuDao());
        return $this->getMenusByIds($menuService, $menuIds, $includeEmptyCode);
    }

    /**
     * 获取所有菜单
     *
     * @param bool $includeEmptyCode 是否包含权限码为空的菜单（不需要标签权限的公共菜单）
     *
     * @return \Illuminate\Database\Eloquent\Collection
     * @throws \Exception
     */
    public function getAllMenus(bool $includeEmptyCode = false): Collection
    {
        /** @var  $siteMenuService SiteMenuService */
        $siteMenuService = Container::make(SiteMenuService::class);
        $menuModel       = $siteMenuService->dao->getModel();

        if ($includeEmptyCode) {
            return $menuModel->where('enabled', 1)
                ->orderBy('sort')
                ->get();
        }

        return $menuModel->where('enabled', 1)
            ->where(function ($q) {
                $q->whereNotNull('code')->where('code', '!=', '');
            })
            ->orderBy('sort')
            ->get();
    }

    /**
     * 根据IDS输出菜单
     *
     * @param \app\service\admin\web\MenuService $menuService
     * @param array                              $ids
     * @param bool                               $includeEmptyCode 是否包含权限码为空的菜单
     *
     * @return \Illuminate\Database\Eloquent\Collection
     * @throws \Exception
     */
    private function getMenusByIds(SiteMenuService $menuService, array $ids, bool $includeEmptyCode = true): Collection
    {
        $menuModel = $menuService->dao->getModel();

        // 如果需要包含权限码为空的菜单
        if ($includeEmptyCode) {
            $query = $menuModel->where('enabled', 1);

            // 如果有指定菜单ID，则包含这些菜单和权限码为空的菜单
            if (!empty($ids)) {
                $query->where(function ($q) use ($ids) {
                    $q->whereIn('id', $ids)->orWhereNull('code')->orWhere('code', '');
                });
            } else {
                // 没有指定菜单ID时，只返回权限码为空的菜单
                $query->where(function ($q) {
                    $q->whereNull('code')->orWhere('code', '');
                });
            }

            return $query->orderBy('sort')->get();
        }

        // 不包含权限码为空的菜单，只返回指定ID的菜单
        if (empty($ids)) {
            return new Collection();
        }

        $chunkSize = 200;
        $chunks    = array_chunk($ids, $chunkSize);

        // 分块查询 + 批量合并（减少内存占用）
        $allResults = new Collection();
        foreach ($chunks as $chunk) {
            $results    = $menuModel
                ->whereIn('id', $chunk)
                ->where('enabled', 1)
                ->orderBy('sort')
                ->get();
            $allResults = $allResults->merge($results);
        }
        return $allResults;
    }

    /**
     * 获取用户角色-权限码codes
     *
     * @param $memberId
     *
     * @return array
     * @throws \Exception
     */
    public function getCodesByUserTags($memberId): array
    {
        if (empty($memberId)) {
            return [];
        }

        $member = $this->dao->find($memberId);
        if (!$member) {
            return [];
        }

        $codes = [];
        $tags  = $member->tags()->where('enabled', 1)->get();

        foreach ($tags as $tag) {
            $permissions = $tag->permissions()->get();
            foreach ($permissions as $permission) {
                if (!empty($permission->code)) {
                    $codes[] = $permission->code;
                }
            }
        }

        return array_unique($codes);
    }
}
