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

use app\model\tenant\WebMenuTemplate;
use core\foundation\base\BaseDao;

/**
 * 前台菜单模板 DAO
 * 操作 saas_template_web_menu 表
 */
class WebMenuTemplateDao extends BaseDao
{
    protected function setModel(): string
    {
        return WebMenuTemplate::class;
    }

    public function selectList(array $where, string|array $field = '*', int $page = 0, int $limit = 0, string $order = '', array $with = [], bool $search = false, ?array $withoutScopes = null): ?\Illuminate\Database\Eloquent\Collection
    {
        if (empty($order)) {
            $order = 'sort asc, id asc';
        }
        return parent::selectList($where, $field, $page, $limit, $order, $with, $search, $withoutScopes);
    }
}
