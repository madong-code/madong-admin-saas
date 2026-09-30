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

use app\model\tenant\TenantResource;
use core\foundation\base\BaseDao;
use Illuminate\Database\Eloquent\Collection;

/**
 * 租户资源使用 DAO
 */
class TenantResourceDao extends BaseDao
{
    protected function setModel(): string
    {
        return TenantResource::class;
    }

    public function getList(array $where = [], string|array $field = '*', int $page = 0, int $limit = 0, string $order = '', array $with = [], bool $search = false, ?array $withoutScopes = null): ?Collection
    {
        return $this->selectList($where, $field, $page, $limit, $order, $with, $search, $withoutScopes);
    }

    public function getByTenantId(int $tenantId): Collection
    {
        return $this->getModel()::where('tenant_id', $tenantId)->get();
    }

    public function getByTenantAndType(int $tenantId, string $type): ?TenantResource
    {
        return $this->getModel()::where('tenant_id', $tenantId)
            ->where('resource_type', $type)
            ->first();
    }
}
