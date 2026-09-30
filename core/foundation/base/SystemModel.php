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


/**
 * 系统模型基类
 * 
 * 不受租户隔离影响的系统级模型
 * 用于系统配置、租户管理、功能订阅等系统表
 * 继承 BaseModel 以获得雪花ID、创建人/更新人等基础功能
 * 
 * 文档位置: docs/saas/08-模型设计.md
 */
class SystemModel extends BaseModel
{
    /**
     * Laravel ORM 时间戳常量
     */
    const CREATED_AT = 'created_at';
    const UPDATED_AT = 'updated_at';
    
    /**
     * 系统模型不受租户隔离影响
     * 覆盖 TenantModel 的全局作用域
     */
    protected static function booted(): void
    {
        parent::booted();
        // 移除租户作用域（如果被继承）
        static::addGlobalScope('system_exempt', function ($builder) {
            // 系统模型不做任何租户过滤
        });
    }
    
    /**
     * 检查当前上下文是否应该应用租户过滤
     * 系统模型始终返回 false
     * 
     * @return bool
     */
    public function shouldApplyTenantScope(): bool
    {
        return false;
    }
}
