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

namespace app\dao\system\menu;

use app\model\system\menu\Menu;
use core\foundation\base\BaseDao;
use core\foundation\exception\handler\BaseException;
use core\foundation\exception\handler\CommonException;

/**
 * 平台菜单
 *
 * @author Mr.April
 * @since  1.0
 */
class MenuDao extends BaseDao
{

    protected function setModel(): string
    {
        return Menu::class;
    }

    public function selectList(array $where, string|array $field = '*', int $page = 0, int $limit = 0, string $order = '', array $with = [], bool $search = false, ?array $withoutScopes = null): ?\Illuminate\Database\Eloquent\Collection
    {
        $order = 'sort';
        return parent::selectList($where, $field, $page, $limit, $order, [], $search, $withoutScopes);
    }

    /**
     * @param string $name
     * @param bool   $force
     *
     * @return mixed
     * @throws \Exception
     */
    public function deleteByPlugin(string $name, bool $force = true): mixed
    {
        $query = $this->getModel()->query();
        $query->where('plugin', $name);
        if (!$force) {
            $query->where('source', 'system');
        }
        return $query->delete();
    }

    /**
     * 安装菜单项
     *
     * @param array  $menuItems
     * @param string $pluginName
     * @param string $parentId
     * @param string $menuType
     *
     * @throws \Throwable
     */
    public function installMenuItems(array $menuItems, string $pluginName, string $parentId = '', string $menuType = 'admin'): void
    {
        try {
            foreach ($menuItems as $menuItem) {
                // 准备菜单数据
                $menuData = [
                    'pid'        => $parentId,
                    'app'        => $menuItem['app'] ?? $menuType,
                    'title'      => $menuItem['title'],
                    'code'       => $menuItem['code'],
                    'level'      => $menuItem['level'] ?? null,
                    'type'       => $menuItem['type'],
                    'sort'       => $menuItem['sort'] ?? 0,
                    'path'       => $menuItem['path'] ?? '',
                    'component'  => $menuItem['component'] ?? '',
                    'redirect'   => $menuItem['redirect'] ?? '',
                    'icon'       => $menuItem['icon'] ?? '',
                    'is_show'    => $menuItem['is_show'] ?? 1,
                    'is_link'    => $menuItem['is_link'] ?? 0,
                    'link_url'   => $menuItem['link_url'] ?? null,
                    'open_type'  => $menuItem['open_type'] ?? 0,
                    'is_cache'   => $menuItem['is_cache'] ?? 0,
                    'is_sync'    => $menuItem['is_sync'] ?? 1,
                    'is_affix'   => $menuItem['is_affix'] ?? 0,
                    'methods'    => $menuItem['methods'] ?? 'GET',
                    'plugin'     => $pluginName, // 记录插件名称
                    'source'     => 'system',    // 标记为系统源
                    'created_at' => time(),
                    'updated_at' => time(),
                ];

                // 创建菜单模型
                $menu = new Menu($menuData);
                $menu->save();
                // 如果有子菜单，递归安装
                if (isset($menuItem['children']) && is_array($menuItem['children'])) {
                    $this->installMenuItems($menuItem['children'], $pluginName, $menu->id, $menuType);
                }
            }
        } catch (\Throwable $e) {
            throw new CommonException($e->getMessage(), $e->getCode());
        }
    }

}
