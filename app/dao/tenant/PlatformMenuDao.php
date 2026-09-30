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
namespace app\dao\tenant;

use app\model\tenant\PlatformMenu;
use core\foundation\base\BaseDao;

/**
 * 平台菜单 DAO
 * 操作 sys_platform_menu 表
 */
class PlatformMenuDao extends BaseDao
{
    protected function setModel(): string
    {
        return PlatformMenu::class;
    }

    public function selectList(array $where, string|array $field = '*', int $page = 0, int $limit = 0, string $order = '', array $with = [], bool $search = false, ?array $withoutScopes = null): ?\Illuminate\Database\Eloquent\Collection
    {
        if (empty($order)) {
            $order = 'sort';
        }
        return parent::selectList($where, $field, $page, $limit, $order, $with, $search, $withoutScopes);
    }
}
