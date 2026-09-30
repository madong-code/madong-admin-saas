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

namespace app\dao\system\config;

use app\model\system\config\Config;
use app\scope\global\AccessPermissionScope;
use core\foundation\base\BaseDao;
use Illuminate\Database\Eloquent\Collection;

class ConfigDao extends BaseDao
{

    protected function setModel(): string
    {
        return Config::class;
    }

    /**
     * 获取配置列表
     *
     * @param array        $where
     * @param string|array $field
     * @param int          $page
     * @param int          $limit
     * @param string       $order
     * @param array        $with
     * @param bool         $search
     * @param array|null   $withoutScopes
     *
     * @return Collection|null
     * @throws \Exception
     */
    public function getList(array $where = [], string|array $field = '*', int $page = 0, int $limit = 0, string $order = '', array $with = [], bool $search = false, ?array $withoutScopes =null): ?Collection
    {
        return $this->selectList($where, $field, $page, $limit, $order, $with, $search, $withoutScopes);
    }
}