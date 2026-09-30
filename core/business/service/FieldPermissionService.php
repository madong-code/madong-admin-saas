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
namespace core\business\service;

use core\infrastructure\cache\CacheService;
use core\business\tenant\context\TenantContext;
use app\model\tenant\TenantColumnPermission;
use app\model\tenant\Subscription;

/**
 * 字段权限服务
 * 
 * 提供字段级访问权限的验证和管理
 * 支持基于订阅的动态字段权限控制
 * 
 * 文档位置: docs/saas/07-功能订阅.md
 */
class FieldPermissionService
{
    /**
     * 缓存 Key 模板
     * @var string
     */
    protected string $cacheKeyTemplate = 'field_permission:{tenant_id}:{module}';
    
    /**
     * 缓存 TTL
     * @var int
     */
    protected int $cacheTtl = 3600;
    
    /**
     * 缓存服务
     * @var CacheService
     */
    protected CacheService $cache;

    public function __construct(CacheService $cache)
    {
        $this->cache = $cache;
    }
    
    /**
     * 检查字段是否有权限
     * 
     * @param string $module 模块名
     * @param string $field 字段名
     * @param int|null $tenantId 租户ID
     * @return bool
     */
    public function isFieldAllowed(string $module, string $field, ?int $tenantId = null): bool
    {
        $tenantId = $tenantId ?? TenantContext::getTenantId();
        
        if ($tenantId === null) {
            return true; // 没有租户上下文，默认允许
        }
        
        // 检查订阅
        $subscription = $this->getSubscription($tenantId);
        
        // 如果订阅允许所有字段，直接返回 true
        if ($this->subscriptionAllowsField($subscription, $module, $field)) {
            return true;
        }
        
        // 检查租户自定义权限
        return SystemColumnPermission::isFieldAllowed($tenantId, $module, $field);
    }
    
    /**
     * 获取允许的字段列表
     * 
     * @param string $module
     * @param int|null $tenantId
     * @return array
     */
    public function getAllowedFields(string $module, ?int $tenantId = null): array
    {
        $tenantId = $tenantId ?? TenantContext::getTenantId();
        
        if ($tenantId === null) {
            return []; // 没有租户上下文，返回空
        }
        
        // 从缓存获取
        $cacheKey = $this->buildCacheKey($tenantId, $module);
        
        try {
            return $this->cache->remember($cacheKey, $this->cacheTtl, function () use ($tenantId, $module) {
                return $this->queryAllowedFields($tenantId, $module);
            });
        } catch (\Exception $e) {
            // 缓存失败，直接查询
            return $this->queryAllowedFields($tenantId, $module);
        }
    }
    
    /**
     * 查询允许的字段
     * 
     * @param int $tenantId
     * @param string $module
     * @return array
     */
    protected function queryAllowedFields(int $tenantId, string $module): array
    {
        $subscriptionFields = $this->getSubscriptionAllowedFields($tenantId, $module);
        $customFields = SystemColumnPermission::getAllowedFields($tenantId, $module);
        $deniedFields = SystemColumnPermission::getDeniedFields($tenantId, $module);
        
        if (empty($subscriptionFields)) {
            $allowedFields = $customFields;
        } elseif (empty($customFields)) {
            $allowedFields = $subscriptionFields;
        } else {
            $allowedFields = array_intersect($subscriptionFields, $customFields);
        }
        
        $allowedFields = array_diff($allowedFields, $deniedFields);
        
        return array_values($allowedFields);
    }
    
    /**
     * 检查订阅是否允许字段
     * 
     * @param array|null $subscription
     * @param string $module
     * @param string $field
     * @return bool
     */
    protected function subscriptionAllowsField(?array $subscription, string $module, string $field): bool
    {
        if (!$subscription) {
            return true; // 没有订阅，允许所有字段
        }
        
        return (new SystemSubscription($subscription))->hasField($module, $field);
    }
    
    /**
     * 获取订阅允许的字段
     * 
     * @param int $tenantId
     * @param string $module
     * @return array
     */
    protected function getSubscriptionAllowedFields(int $tenantId, string $module): array
    {
        $subscription = $this->getSubscription($tenantId);
        
        if (!$subscription) {
            return [];
        }
        
        $subscriptionModel = new SystemSubscription($subscription);
        $fields = $subscriptionModel->getFieldPermission($module);
        
        if ($fields === '*' || $fields === ['*'] || empty($fields)) {
            return []; // 空表示允许所有
        }
        
        return is_array($fields) ? $fields : [];
    }
    
    /**
     * 获取订阅信息
     * 
     * @param int $tenantId
     * @return array|null
     */
    protected function getSubscription(int $tenantId): ?array
    {
        $subscriptionModel = SystemSubscription::getActiveSubscription($tenantId);
        return $subscriptionModel ? $subscriptionModel->toArray() : null;
    }
    
    /**
     * 过滤数据字段
     * 
     * @param array $data
     * @param string $module
     * @param int|null $tenantId
     * @return array
     */
    public function filterFields(array $data, string $module, ?int $tenantId = null): array
    {
        $allowedFields = $this->getAllowedFields($module, $tenantId);
        
        if (empty($allowedFields)) {
            return $data; // 没有限制，返回原数据
        }
        
        return array_intersect_key($data, array_flip($allowedFields));
    }
    
    /**
     * 过滤模型数据
     * 
     * @param object $model
     * @param string $module
     * @param int|null $tenantId
     * @return array
     */
    public function filterModel(object $model, string $module, ?int $tenantId = null): array
    {
        $data = $model->toArray();
        return $this->filterFields($data, $module, $tenantId);
    }
    
    /**
     * 批量验证字段权限
     * 
     * @param array $fields
     * @param string $module
     * @param int|null $tenantId
     * @return array ['allowed' => [], 'denied' => []]
     */
    public function batchCheckFields(array $fields, string $module, ?int $tenantId = null): array
    {
        $result = [
            'allowed' => [],
            'denied' => [],
        ];
        
        foreach ($fields as $field) {
            if ($this->isFieldAllowed($module, $field, $tenantId)) {
                $result['allowed'][] = $field;
            } else {
                $result['denied'][] = $field;
            }
        }
        
        return $result;
    }
    
    /**
     * 设置字段权限
     * 
     * @param int $tenantId
     * @param string $module
     * @param array $fields
     * @param int $isAllowed
     * @param string|null $subscriptionId
     * @return int
     */
    public function setFields(int $tenantId, string $module, array $fields, int $isAllowed, ?string $subscriptionId = null): int
    {
        $count = SystemColumnPermission::batchSetFields($tenantId, $module, $fields, $isAllowed, $subscriptionId);
        
        // 清除缓存
        $this->clearCache($tenantId, $module);
        
        return $count;
    }
    
    /**
     * 允许字段
     * 
     * @param int $tenantId
     * @param string $module
     * @param array $fields
     * @param string|null $subscriptionId
     * @return int
     */
    public function allowFields(int $tenantId, string $module, array $fields, ?string $subscriptionId = null): int
    {
        return $this->setFields($tenantId, $module, $fields, SystemColumnPermission::ALLOWED, $subscriptionId);
    }
    
    /**
     * 禁止字段
     * 
     * @param int $tenantId
     * @param string $module
     * @param array $fields
     * @param string|null $subscriptionId
     * @return int
     */
    public function denyFields(int $tenantId, string $module, array $fields, ?string $subscriptionId = null): int
    {
        return $this->setFields($tenantId, $module, $fields, SystemColumnPermission::NOT_ALLOWED, $subscriptionId);
    }
    
    /**
     * 清除模块权限
     * 
     * @param int $tenantId
     * @param string $module
     * @return int
     */
    public function clearModulePermissions(int $tenantId, string $module): int
    {
        $count = SystemColumnPermission::clearModulePermissions($tenantId, $module);
        
        // 清除缓存
        $this->clearCache($tenantId, $module);
        
        return $count;
    }
    
    /**
     * 从套餐同步权限
     * 
     * @param int $tenantId
     * @param string $planId
     * @return void
     */
    public function syncFromPlan(int $tenantId, string $planId): void
    {
        $planConfig = SystemSubscription::getPlanConfig($planId);
        
        if (!$planConfig) {
            return;
        }
        
        // 清除旧权限
        SystemColumnPermission::clearAllPermissions($tenantId);
        
        // 根据套餐设置字段权限
        $allowedFields = $planConfig['allowed_fields'] ?? [];
        
        if ($allowedFields === '*') {
            return; // 允许所有字段
        }
        
        // 为每个模块设置允许的字段
        foreach ($allowedFields as $module => $fields) {
            if ($fields === '*') {
                continue; // 该模块允许所有字段
            }
            
            $this->allowFields($tenantId, $module, $fields, $planId);
        }
    }
    
    /**
     * 获取权限统计
     * 
     * @param int $tenantId
     * @return array
     */
    public function getStats(int $tenantId): array
    {
        return SystemColumnPermission::getPermissionStats($tenantId);
    }
    
    /**
     * 导出权限配置
     * 
     * @param int $tenantId
     * @return array
     */
    public function export(int $tenantId): array
    {
        return SystemColumnPermission::exportPermissions($tenantId);
    }
    
    /**
     * 导入权限配置
     * 
     * @param int $tenantId
     * @param array $permissions
     * @return int
     */
    public function import(int $tenantId, array $permissions): int
    {
        // 清除旧权限
        SystemColumnPermission::clearAllPermissions($tenantId);
        
        // 导入新权限
        $count = SystemColumnPermission::importPermissions($tenantId, $permissions);
        
        // 清除所有相关缓存
        $this->clearAllCache($tenantId);
        
        return $count;
    }
    
    /**
     * 获取模块的默认字段
     * 
     * @param string $module
     * @return array
     */
    public function getDefaultFields(string $module): array
    {
        $defaults = config('tenant.subscription.default_fields', []);
        return $defaults[$module] ?? [];
    }
    
    /**
     * 获取可用模块列表
     * 
     * @return array
     */
    public function getAvailableModules(): array
    {
        return array_keys(config('tenant.subscription.default_fields', []));
    }
    
    /**
     * 验证数据写入权限
     * 
     * @param array $data
     * @param string $module
     * @param int|null $tenantId
     * @return array ['valid' => bool, 'errors' => []]
     */
    public function validateWritePermission(array $data, string $module, ?int $tenantId = null): array
    {
        $errors = [];
        
        foreach (array_keys($data) as $field) {
            if (!$this->isFieldAllowed($module, $field, $tenantId)) {
                $errors[] = "Field '{$field}' is not allowed in module '{$module}'";
            }
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }
    
    /**
     * 构建缓存 Key
     * 
     * @param int $tenantId
     * @param string $module
     * @return string
     */
    protected function buildCacheKey(int $tenantId, string $module): string
    {
        return str_replace(['{tenant_id}', '{module}'], [(string) $tenantId, $module], $this->cacheKeyTemplate);
    }
    
    /**
     * 清除缓存
     * 
     * @param int $tenantId
     * @param string $module
     * @return void
     */
    public function clearCache(int $tenantId, string $module): void
    {
        $cacheKey = $this->buildCacheKey($tenantId, $module);
        $this->cache->delete($cacheKey);
    }
    
    /**
     * 清除所有相关缓存
     * 
     * @param int $tenantId
     * @return void
     */
    public function clearAllCache(int $tenantId): void
    {
        $modules = $this->getAvailableModules();
        
        foreach ($modules as $module) {
            $this->clearCache($tenantId, $module);
        }
    }
    
    /**
     * 检查用户是否有权限访问模块
     * 
     * @param string $module
     * @param int|null $tenantId
     * @return bool
     */
    public function hasModulePermission(string $module, ?int $tenantId = null): bool
    {
        $tenantId = $tenantId ?? TenantContext::getTenantId();
        
        if ($tenantId === null) {
            return true;
        }
        
        $subscription = $this->getSubscription($tenantId);
        
        if (!$subscription) {
            return true; // 没有订阅，允许访问
        }
        
        return (new SystemSubscription($subscription))->hasModule($module);
    }
    
    /**
     * 获取模块的完整权限信息
     * 
     * @param string $module
     * @param int|null $tenantId
     * @return array
     */
    public function getModulePermissionInfo(string $module, ?int $tenantId = null): array
    {
        $tenantId = $tenantId ?? TenantContext::getTenantId();
        
        $subscription = $this->getSubscription($tenantId);
        $subscriptionModel = $subscription ? new SystemSubscription($subscription) : null;
        
        $moduleAllowed = $subscriptionModel ? $subscriptionModel->hasModule($module) : true;
        $defaultFields = $this->getDefaultFields($module);
        $allowedFields = $this->getAllowedFields($module, $tenantId);
        
        return [
            'module' => $module,
            'has_module_permission' => $moduleAllowed,
            'allowed_fields' => $allowedFields,
            'field_count' => count($allowedFields),
            'default_fields' => $defaultFields,
        ];
    }
}
