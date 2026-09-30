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
namespace core\business\tenant\base;

use core\business\tenant\context\TenantContext;
use core\foundation\exception\handler\TenantException;
use core\foundation\base\BaseModel;
use Illuminate\Database\Eloquent\Builder;

/**
 * 租户模型基类
 * 
 * 所有需要租户隔离的业务模型都应继承此类
 * 自动添加 tenant_id 字段的过滤和写入
 * 使用 Laravel 全局作用域
 */
class TenantModel extends BaseModel
{
    /**
     * Laravel ORM 时间戳常量
     */
    const CREATED_AT = 'created_at';
    const UPDATED_AT = 'updated_at';
    
    /**
     * 注册租户全局作用域
     */
    protected static function booted(): void
    {
        // 添加租户隔离全局作用域
        static::addGlobalScope('tenant_scope', function (Builder $builder) {
            // SINGLE（非租户）模式，不应用任何租户作用域
            if (TenantContext::isSingleMode()) {
                return;
            }
            
            // 检查是否为系统管理员（显式 setAdminMode(true) 始终跳过租户隔离）
            if (TenantContext::isAdmin()) {
                return;
            }
            
            // 检查当前隔离模式
            $mode = TenantContext::getIsolationMode();
            if ($mode === 'database') {
                return;
            }
            
            // 检查是否在白名单中
            $model = new static();
            if ($model->isInWhitelist()) {
                return;
            }
            
            $tenantId = TenantContext::getTenantId();
            
            if ($tenantId === null && $model->forceTenantIsolation) {
                throw new TenantException('Tenant ID is required for this operation');
            }
            
            if ($tenantId !== null) {
                $builder->where($model->getTenantColumn(), $tenantId);
            }
        });

        // 注册创建事件：自动设置租户ID（SINGLE 模式跳过）
        static::creating(function ($model) {
            if (TenantContext::isSingleMode()) {
                return;
            }
            $tenantId = TenantContext::getTenantId();
            if ($tenantId !== null) {
                $model->setAttribute($model->getTenantColumn(), $tenantId);
            }
        });
        
        // 注册更新事件：跨租户检查（SINGLE 模式跳过）
        static::updating(function ($model) {
            if (TenantContext::isSingleMode()) {
                return;
            }
            if (!$model->allowCrossTenant) {
                static::checkCrossTenantAccess($model);
            }
        });
        
        // 注册删除事件：跨租户检查（SINGLE 模式跳过）
        static::deleting(function ($model) {
            if (TenantContext::isSingleMode()) {
                return;
            }
            if (!$model->allowCrossTenant) {
                static::checkCrossTenantAccess($model);
            }
        });
    }
    
    /**
     * 租户标识字段名
     * @var string
     */
    protected $tenantColumn = 'tenant_id';
    
    /**
     * 是否强制租户隔离
     * @var bool
     */
    protected $forceTenantIsolation = true;
    
    /**
     * 是否允许跨租户操作
     * @var bool
     */
    protected $allowCrossTenant = false;
    
    /**
     * 检查是否在白名单中
     * 
     * @return bool
     */
    protected function isInWhitelist(): bool
    {
        $whitelist = config('tenant.field_isolation.whitelist', []);
        return in_array(static::class, $whitelist);
    }
    
    /**
     * 获取租户标识字段名
     * 
     * @return string
     */
    public function getTenantColumn(): string
    {
        return $this->tenantColumn;
    }
    
    /**
     * 检查跨租户访问
     * 
     * @param static $model
     * @return void
     * @throws TenantException
     */
    protected static function checkCrossTenantAccess($model): void
    {
        $tenantId = TenantContext::getTenantId();
        
        if ($tenantId === null) {
            return;
        }
        
        $tenantColumn = $model->getTenantColumn();
        
        // 检查是否允许跨租户访问
        $allowCrossTenant = config('tenant.security.cross_tenant_access.allow_write', false);
        if (!$allowCrossTenant && !$model->allowCrossTenant) {
            $pk = $model->getPk();
            $id = $model->getData($pk);
            
            if ($id !== null) {
                $currentTenantId = $model->newQuery()
                    ->withoutGlobalScopes()
                    ->where($pk, $id)
                    ->value($tenantColumn);
                    
                if ($currentTenantId != $tenantId) {
                    throw new TenantException('Cross-tenant access is not allowed');
                }
            }
        }
    }
    
    /**
     * 不带租户隔离的查询
     * 用于管理员操作
     * 
     * @param callable $callback
     * @return mixed
     */
    public static function withoutTenantScope(callable $callback)
    {
        $originalTenantId = TenantContext::getTenantId();
        
        try {
            TenantContext::setAdminMode(true);
            TenantContext::clear();
            return $callback();
        } finally {
            TenantContext::setAdminMode(false);
            if ($originalTenantId !== null) {
                TenantContext::setTenant($originalTenantId);
            }
        }
    }
    
    /**
     * 跨租户查询 (需谨慎使用)
     * 
     * @param callable $callback
     * @return mixed
     */
    public static function withCrossTenant(callable $callback)
    {
        $originalTenantId = TenantContext::getTenantId();
        
        try {
            TenantContext::clear();
            return $callback();
        } finally {
            if ($originalTenantId !== null) {
                TenantContext::setTenant($originalTenantId);
            }
        }
    }
    
    /**
     * 指定租户ID查询
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
     * 获取当前租户ID
     * 
     * @return int|string|null
     */
    public function getCurrentTenantId()
    {
        return TenantContext::getTenantId();
    }
    
    /**
     * 检查是否属于当前租户
     * 
     * @return bool
     */
    public function belongsToCurrentTenant(): bool
    {
        $tenantId = TenantContext::getTenantId();
        $dataTenantId = $this->getData($this->getTenantColumn());
        
        return $tenantId !== null && $tenantId == $dataTenantId;
    }
    
    /**
     * 允许跨租户操作
     * 
     * @param bool $allow
     * @return $this
     */
    public function allowCrossTenant(bool $allow = true): self
    {
        $this->allowCrossTenant = $allow;
        return $this;
    }
    
    /**
     * 强制租户隔离
     * 
     * @param bool $force
     * @return $this
     */
    public function forceTenantIsolation(bool $force = true): self
    {
        $this->forceTenantIsolation = $force;
        return $this;
    }
    
    /**
     * 动态设置租户列名
     * 
     * @param string $column
     * @return $this
     */
    public function setTenantColumn(string $column): self
    {
        $this->tenantColumn = $column;
        return $this;
    }
    
    /**
     * 获取所属租户ID
     * 
     * @return int|string|null
     */
    public function getTenantId()
    {
        return $this->getData($this->getTenantColumn());
    }
    
    /**
     * 设置所属租户ID
     * 
     * @param int|string $tenantId
     * @return $this
     */
    public function setTenantId($tenantId): self
    {
        $this->setAttribute($this->getTenantColumn(), $tenantId);
        return $this;
    }

    /**
     * 检查是否应该应用租户隔离
     * 
     * @return bool
     */
    public function shouldApplyTenantScope(): bool
    {
        // SINGLE（非租户）模式，不应用租户作用域
        if (TenantContext::isSingleMode()) {
            return false;
        }
        
        // 检查是否为系统管理员（显式 setAdminMode(true) 始终跳过租户隔离）
        if (TenantContext::isAdmin()) {
            return false;
        }
        
        // 检查当前隔离模式
        $mode = TenantContext::getIsolationMode();
        if ($mode === 'database') {
            return false;
        }
        
        // 检查是否在白名单中
        if ($this->isInWhitelist()) {
            return false;
        }
        
        return true;
    }

    /**
     * 获取数据库连接名
     *
     * database 模式下自动切换到对应租户的数据库连接
     * field/single 模式使用默认连接
     *
     * @return string|null
     */
    public function getConnectionName(): ?string
    {
        // database 模式：使用租户专属连接
        if (TenantContext::getIsolationMode() === 'database') {
            $connectionName = TenantContext::getConnectionName();
            if ($connectionName !== null) {
                return $connectionName;
            }
        }
        
        // field / single 模式：使用模型默认连接
        return $this->connection;
    }
}
