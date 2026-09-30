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
namespace core\business\tenant\scope;

use core\foundation\exception\handler\TenantException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use core\business\tenant\context\TenantContext;
use core\foundation\base\SystemModel;
use core\business\tenant\base\TenantModel;
use Illuminate\Database\Eloquent\Scope;

/**
 * 租户全局作用域
 * 
 * Laravel Eloquent 全局作用域
 * 用于全局自动添加租户过滤
 * 支持字段隔离和库隔离两种模式
 * 
 * 文档位置: docs/saas/04-字段隔离.md
 */
class TenantScope implements Scope
{
    /**
     * 全局作用域名称
     */
    const GLOBAL_SCOPE = 'tenant';

    /**
     * 应用作用域到查询构建器
     *
     * @param Builder $builder
     * @param Model   $model
     *
     * @return void
     * @throws \core\foundation\exception\handler\TenantException
     */
    public function apply(Builder $builder, Model $model): void
    {
        // SINGLE 模式下，不应用任何租户作用域
        if (TenantContext::isSingleMode()) {
            return;
        }

        // 超级管理员模式（AdminMode）：跳过租户过滤，
        // 使 withoutTenantFilter() 和 admin_bypass 配置生效
        if (TenantContext::isSuperAdmin()) {
            return;
        }
        
        // 检查隔离模式
        $mode = TenantContext::getIsolationMode();
        if ($mode === 'database') {
            // 库隔离模式下，不需要字段过滤
            return;
        }
        
        // 检查白名单
        if ($this->isWhitelistedModel($model)) {
            return;
        }
        
        $tenantId = TenantContext::getTenantId();
        
        if ($tenantId === null) {
            // 未设置租户ID时，检查是否强制隔离
            if ($this->isForceIsolation($model)) {
                throw new TenantException('Tenant ID is required for this operation');
            }
            return;
        }
        
        $column = $this->getTenantColumn($model);
        $table = $model->getTable();
        $qualifiedColumn = $table . '.' . $column;

        // 如果模型允许 NULL 租户表示"全局共享"，则同时匹配当前租户和全局记录
        if (property_exists($model, 'allowNullTenant') && $model->allowNullTenant) {
            $builder->where(function (Builder $query) use ($qualifiedColumn, $tenantId) {
                $query->where($qualifiedColumn, $tenantId)
                      ->orWhereNull($qualifiedColumn);
            });
        } else {
            // 加表前缀避免 JOIN 时多表同名列歧义 (如 sys_role JOIN sys_admin_role)
            $builder->where($qualifiedColumn, $tenantId);
        }
    }

    /**
     * 检查是否为强制隔离模式
     *
     * @param Model $model
     * @return bool
     */
    protected function isForceIsolation(Model $model): bool
    {
        // 检查模型是否有 forceTenantIsolation 属性
        if (property_exists($model, 'forceTenantIsolation')) {
            return $model->forceTenantIsolation;
        }
        
        // 根据模型类型判断是否强制隔离
        $forceTables = config('tenant.field_isolation.force_tables', []);
        
        return in_array($model->getTable(), $forceTables);
    }

    /**
     * 检查模型是否在白名单中
     *
     * @param Model $model
     * @return bool
     */
    protected function isWhitelistedModel(Model $model): bool
    {
        // SystemModel 不需要租户隔离
        if ($model instanceof SystemModel) {
            return true;
        }
        
        $whitelist = config('tenant.field_isolation.whitelist', []);
        return in_array(get_class($model), $whitelist);
    }

    /**
     * 获取租户列名
     *
     * @param Model $model
     * @return string
     */
    protected function getTenantColumn(Model $model): string
    {
        // 检查模型是否有 getTenantColumn 方法
        if (method_exists($model, 'getTenantColumn')) {
            return $model->getTenantColumn();
        }
        
        // 检查模型是否有 tenantColumn 属性
        if (property_exists($model, 'tenantColumn')) {
            return $model->tenantColumn;
        }
        
        return config('tenant.field_isolation.tenant_column', 'tenant_id');
    }

    /**
     * 检查查询是否为跨租户查询
     *
     * @param Model $model
     * @param int|string $targetTenantId
     * @return bool
     */
    public static function isCrossTenantQuery(Model $model, $targetTenantId): bool
    {
        $currentTenantId = TenantContext::getTenantId();
        
        if ($currentTenantId === null) {
            return true; // 没有当前租户ID，视为跨租户
        }
        
        return $currentTenantId != $targetTenantId;
    }

    /**
     * 验证跨租户访问权限
     *
     * @param Model $model
     * @param int|string $targetTenantId
     * @return void
     * @throws TenantException
     */
    public static function validateCrossTenantAccess(Model $model, $targetTenantId): void
    {
        $allowCrossTenant = config('tenant.security.cross_tenant_access.allow_query', false);
        
        if (!$allowCrossTenant && self::isCrossTenantQuery($model, $targetTenantId)) {
            throw new TenantException('Cross-tenant query is not allowed');
        }
    }

    /**
     * 创建不带租户过滤的查询
     *
     * @param callable $callback
     * @return mixed
     */
    public static function withoutTenantFilter(callable $callback)
    {
        $originalTenantId = TenantContext::getTenantId();
        
        try {
            TenantContext::setAdminMode(true);
            return $callback();
        } finally {
            TenantContext::setAdminMode(false);
            if ($originalTenantId !== null) {
                TenantContext::setTenant($originalTenantId);
            }
        }
    }

    /**
     * 为指定租户创建查询
     *
     * @param int|string $tenantId
     * @param callable $callback
     * @return mixed
     */
    public static function forTenant($tenantId, callable $callback)
    {
        $originalTenantId = TenantContext::getTenantId();
        
        try {
            TenantContext::setTenant($tenantId);
            return $callback();
        } finally {
            if ($originalTenantId !== null) {
                TenantContext::setTenant($originalTenantId);
            } else {
                TenantContext::clear();
            }
        }
    }

    /**
     * 获取租户过滤条件
     *
     * @param Model|null $model
     * @return array
     */
    public static function getTenantCondition(?Model $model = null): array
    {
        $tenantId = TenantContext::getTenantId();
        
        if ($tenantId === null) {
            return [];
        }
        
        $column = 'tenant_id';
        if ($model !== null && method_exists($model, 'getTenantColumn')) {
            $column = $model->getTenantColumn();
        }
        
        return [$column => $tenantId];
    }


    /**
     * 检查当前模型是否支持租户隔离
     *
     * @param string|object $model
     * @return bool
     */
    public static function isTenantAware($model): bool
    {
        if (is_string($model)) {
            $model = new $model();
        }
        
        // SystemModel 不需要租户隔离
        if ($model instanceof SystemModel) {
            return false;
        }
        
        // TenantModel 支持租户隔离
        if ($model instanceof TenantModel) {
            return true;
        }
        
        // 检查是否有 tenant_id 字段
        if (property_exists($model, 'tenantColumn') || 
            property_exists($model, 'tenant_column')) {
            return true;
        }
        
        // 检查模型是否有 getTenantColumn 方法
        if (method_exists($model, 'getTenantColumn')) {
            return true;
        }
        
        return false;
    }
}
