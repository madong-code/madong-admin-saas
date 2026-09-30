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

namespace app\dao\system\recycle;

use app\model\system\recycle\RecycleBin;
use core\foundation\base\BaseDao;

/**
 * 回收站
 *
 * @author Mr.April
 * @since  1.0
 */
class RecycleBinDao extends BaseDao
{

    protected function setModel(): string
    {
        return RecycleBin::class;
    }

    public function selectList(array $where, string|array $field = '*', int $page = 0, int $limit = 0, string $order = '', array $with = [], bool $search = false, ?array $withoutScopes = null): ?\Illuminate\Database\Eloquent\Collection
    {
        return parent::selectList($where, $field, $page, $limit, $order, ['operate'], $search, $withoutScopes);
    }

    public function get($id, ?array $field = null, ?array $with = [], string $order = '', ?array $withoutScopes = null): RecycleBin
    {
        return parent::get($id, ['*'], ['operate'], '', $withoutScopes);
    }

}
