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

use app\model\tenant\TenantLog;
use core\foundation\base\BaseDao;
use Illuminate\Database\Eloquent\Collection;

/**
 * 租户操作日志 DAO
 */
class TenantLogDao extends BaseDao
{
    protected function setModel(): string
    {
        return TenantLog::class;
    }

    public function getList(array $where = [], string|array $field = '*', int $page = 0, int $limit = 0, string $order = 'created_at desc', array $with = [], bool $search = false, ?array $withoutScopes = null): ?Collection
    {
        return $this->selectList($where, $field, $page, $limit, $order, $with, $search, $withoutScopes);
    }

    public function getByTenantId(int $tenantId, int $limit = 20): ?Collection
    {
        return $this->getModel()::where('tenant_id', $tenantId)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }
}
