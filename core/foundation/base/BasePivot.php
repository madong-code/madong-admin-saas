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
namespace core\foundation\base;

use app\scope\global\TenantScope;
use core\business\tenant\context\TenantContext;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * 中间表（Pivot）模型基类
 *
 * 用于多对多关联的中间表，继承 Laravel Pivot 以获得标准中间表行为。
 * 自动处理 tenant_id 填充和租户隔离作用域，与 BaseModel 保持一致。
 *
 * @author Mr.April
 * @since  1.0
 */
class BasePivot extends Pivot
{
    /**
     * 指示是否自动维护时间戳
     * 中间表通常不需要时间戳
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * 隐藏 tenant_id，前端返回时不可见
     *
     * @var array
     */
    protected $hidden = ['tenant_id'];

    protected static function booted(): void
    {
        // 非单体模式才添加租户作用域
        if (!TenantContext::isSingleMode()) {
            static::addGlobalScope(new TenantScope());
        }
    }

    protected static function boot(): void
    {
        parent::boot();

        // 非单体模式才注册 tenant_id 自动填充
        if (!TenantContext::isSingleMode()) {
            static::creating(function ($model) {
                if (TenantContext::isTenantEnabled()
                    && TenantContext::isInitialized()
                    && !TenantContext::isSuperAdmin()
                    && empty($model->tenant_id)
                ) {
                    $model->tenant_id = TenantContext::getTenantId();
                }
            });
        }
    }

    /**
     * 动态获取数据库连接名
     * 支持按租户隔离模式切换数据源
     */
    public function getConnectionName()
    {
        // 显式指定了连接名
        if (isset($this->connection)) {
            return $this->connection;
        }

        // 租户上下文已初始化，返回租户专属连接名
        if (TenantContext::isTenantEnabled() && TenantContext::isInitialized()) {
            $connName = TenantContext::getConnectionName();
            if ($connName) {
                return $connName;
            }
        }

        return parent::getConnectionName();
    }
}
