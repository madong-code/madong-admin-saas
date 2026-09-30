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
namespace app\service\admin\web;

use app\dao\web\MenuDao;
use app\model\web\Menu;
use core\foundation\base\BaseService;

/**
 * 菜单服务类
 */
class MenuService extends BaseService
{
    /**
     * 构造方法
     */
    public function __construct(MenuDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 批量删除菜单（含子菜单）
     *
     * @param array $ids 菜单ID数组
     *
     * @return array 被删除的所有菜单ID
     * @throws \Throwable
     */
    public function batchDelete(array $ids): array
    {
        return $this->transaction(function () use ($ids) {
            $deletedIds = [];

            foreach ($ids as $id) {
                /** @var Menu $item */
                $item = $this->get($id);
                if (!$item) {
                    continue;
                }
                // 删除菜单及子菜单，获取所有被删除的ID
                $result = $item->deleteWithAllChildren();
                if (is_array($result)) {
                    $deletedIds = array_merge($deletedIds, $result);
                }
            }

            return array_unique($deletedIds);
        });
    }
}
