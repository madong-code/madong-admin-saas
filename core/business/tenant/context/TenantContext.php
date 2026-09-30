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
namespace core\business\tenant\context;

use app\model\tenant\Tenant;
use core\foundation\exception\handler\TenantException;
use core\infrastructure\cache\CacheService;
use Illuminate\Support\Facades\Cache;

/**
 * 租户上下文管理器
 * 
 * 负责管理当前请求的租户上下文信息
 * 实现租户信息的获取、设置、清除等操作
 * 使用 Laravel Cache
 */
class TenantContext
{
    /**
     * 当前租户ID
     * @var int|string|null
     */
    protected static $tenantId;
    
    /**
     * 当前租户信息
     * @var array|null
     */
    protected static $tenantInfo;
    
    /**
     * 隔离模式
     * @var string
     */
    protected static $isolationMode;
    
    /**
     * 是否为系统管理员
     * @var bool
     */
    protected static $isAdmin = false;
    
    /**
     * 上下文数据存储
     * @var array
     */
    protected static $data = [];
    
    /**
     * 内存缓存（用于避免数据库查询）
     * @var array
     */
    protected static $memoryCache = [];
    
    /**
     * 初始化上下文
     * 
     * @param int|string|null $tenantId
     * @param array|null $tenantInfo
     * @return void
     */
    public static function init($tenantId = null, ?array $tenantInfo = null): void
    {
        self::$tenantId = $tenantId;
        self::$tenantInfo = $tenantInfo;
        self::$isolationMode = null;
        self::$data = [];
    }
    
    /**
     * 设置当前租户ID
     * 
     * @param int|string $tenantId
     * @param array|null $tenantInfo
     * @param bool $autoLoad 是否自动从数据库加载租户信息
     * @return void
     */
    public static function setTenant($tenantId, ?array $tenantInfo = null, bool $autoLoad = true): void
    {
        self::validateTenantId($tenantId);
        self::$tenantId = $tenantId;
        
        if ($tenantInfo !== null) {
            self::$tenantInfo = $tenantInfo;
        } elseif ($autoLoad && (self::$tenantInfo === null || (self::$tenantInfo['id'] ?? self::$tenantInfo['tenant_id'] ?? null) != $tenantId)) {
            // 自动加载租户信息
            self::loadTenantInfo($tenantId);
        }
    }
    
    /**
     * 获取当前租户ID
     * 
     * @return int|string|null
     */
    public static function getTenantId()
    {
        return self::$tenantId;
    }
    
    /**
     * 获取当前租户信息
     * 
     * @return array|null
     */
    public static function getTenantInfo(): ?array
    {
        if (self::$tenantId !== null && self::$tenantInfo === null) {
            self::loadTenantInfo(self::$tenantId);
        }
        return self::$tenantInfo;
    }
    
    /**
     * 获取租户ID (支持默认值)
     * 
     * @param int|string|null $default
     * @return int|string|null
     */
    public static function getTenantIdOrDefault($default = null)
    {
        return self::$tenantId ?? $default;
    }
    
    /**
     * 检查是否设置了租户
     * 
     * @return bool
     */
    public static function hasTenant(): bool
    {
        if (self::isSingleMode()) {
            return false;
        }
        return self::$tenantId !== null;
    }
    
    /**
     * 设置隔离模式
     * 
     * @param string $mode field|database
     * @return void
     */
    public static function setIsolationMode(string $mode): void
    {
        $validModes = ['field', 'database'];
        if (!in_array($mode, $validModes)) {
            throw new TenantException("Invalid isolation mode: {$mode}");
        }
        self::$isolationMode = $mode;
    }
    
    /**
     * 获取隔离模式
     * 
     * @return string
     */
    public static function getIsolationMode(): string
    {
        if (self::isSingleMode()) {
            return 'single';
        }
        if (self::$isolationMode !== null) {
            return self::$isolationMode;
        }
        return config('tenant.default_mode', 'field');
    }
    
    /**
     * 设置管理员模式
     * 
     * @param bool $isAdmin
     * @return void
     */
    public static function setAdminMode(bool $isAdmin): void
    {
        self::$isAdmin = $isAdmin;
    }
    
    /**
     * 是否为管理员
     * 
     * @return bool
     */
    public static function isAdmin(): bool
    {
        return self::$isAdmin;
    }
    
    /**
     * 设置上下文数据
     * 
     * @param string $key
     * @param mixed $value
     * @return void
     */
    public static function setData(string $key, $value): void
    {
        self::$data[$key] = $value;
    }
    
    /**
     * 获取上下文数据
     * 
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public static function getData(string $key, $default = null)
    {
        return self::$data[$key] ?? $default;
    }
    
    /**
     * 检查上下文数据是否存在
     * 
     * @param string $key
     * @return bool
     */
    public static function hasData(string $key): bool
    {
        return isset(self::$data[$key]);
    }
    
    /**
     * 清除上下文数据
     * 
     * @param string|null $key
     * @return void
     */
    public static function clearData(?string $key = null): void
    {
        if ($key === null) {
            self::$data = [];
        } else {
            unset(self::$data[$key]);
        }
    }
    
    /**
     * 清除租户上下文
     * 
     * @return void
     */
    public static function clear(): void
    {
        self::$tenantId = null;
        self::$tenantInfo = null;
        self::$isolationMode = null;
        self::$isAdmin = false;
        self::$data = [];
        self::$memoryCache = [];
    }
    
    /**
     * 加载租户信息
     * 
     * @param int|string $tenantId
     * @return void
     */
    protected static function loadTenantInfo($tenantId): void
    {
        // 检查内存缓存
        $cacheKey = "tenant_info_{$tenantId}";
        if (isset(self::$memoryCache[$cacheKey])) {
            self::$tenantInfo = self::$memoryCache[$cacheKey];
            return;
        }
        
        // 检查是否启用缓存
        $useCache = config('tenant.cache.enabled', true);
        $cacheTtl = config('tenant.cache.ttl', 3600);
        
        try {
            if ($useCache) {
                // 使用 Laravel Cache
                $cacheConfigKey = config('tenant.cache.tenant_config_key', 'tenant:config:{tenant_id}');
                $cacheKeyFull = str_replace('{tenant_id}', (string) $tenantId, $cacheConfigKey);
                
                // 尝试从缓存获取
                $cached = Cache::get($cacheKeyFull);
                if ($cached !== null) {
                    self::$memoryCache[$cacheKey] = $cached;
                    self::$tenantInfo = $cached;
                    return;
                }
                
                // 从数据库加载
                $tenant = Tenant::withoutGlobalScopes()->find($tenantId);
                $tenantInfo = $tenant ? self::tenantToInternalArray($tenant) : null;
                
                // 写入缓存
                Cache::put($cacheKeyFull, $tenantInfo, $cacheTtl);
                
                self::$memoryCache[$cacheKey] = $tenantInfo;
                self::$tenantInfo = $tenantInfo;
            } else {
                // 不使用缓存，直接从数据库加载
                $tenant = Tenant::withoutGlobalScopes()->find($tenantId);
                self::$tenantInfo = $tenant ? self::tenantToInternalArray($tenant) : null;
            }
        } catch (\Exception $e) {
            // 出错时直接从数据库加载
            try {
                $tenant = Tenant::withoutGlobalScopes()->find($tenantId);
                self::$tenantInfo = $tenant ? self::tenantToInternalArray($tenant) : null;
            } catch (\Exception $e2) {
                // 模型类不存在或数据库连接失败
                self::$tenantInfo = null;
            }
        }
    }
    
    /**
     * 将租户模型转为内部数组（保留 DbSetting 密码等隐藏字段）
     *
     * DbSetting 模型有 $hidden = ['password']，toArray() 会抹掉密码，
     * 而 buildConnectionConfig() 需要完整的连接参数。此方法在 toArray() 后
     * 从模型对象中手动补充隐藏字段。
     *
     * @param \app\model\tenant\Tenant $tenant
     * @return array
     */
    protected static function tenantToInternalArray(\app\model\tenant\Tenant $tenant): array
    {
        $data = $tenant->toArray();

        // 如果关联了 dbSetting，补充被 $hidden 抹掉的字段
        if ($tenant->relationLoaded('dbSetting') && $tenant->dbSetting) {
            $dbSettingArray = $data['db_setting'] ?? [];
            $dbSettingModel = $tenant->dbSetting;

            // 补充 DbSetting 中被 $hidden 隐藏的字段
            foreach ($dbSettingModel->getHidden() as $hiddenField) {
                $dbSettingArray[$hiddenField] = $dbSettingModel->getAttribute($hiddenField);
            }

            $data['db_setting'] = $dbSettingArray;
        }

        return $data;
    }

    /**
     * 获取租户模型
     * 
     * @return string
     */
    protected static function getTenantModel(): string
    {
        return config('tenant.models.tenant', Tenant::class);
    }
    
    /**
     * 验证租户ID
     * 
     * @param int|string $tenantId
     * @return void
     * @throws TenantException
     */
    protected static function validateTenantId($tenantId): void
    {
        if ($tenantId === null || $tenantId === '') {
            throw new TenantException('Tenant ID cannot be empty');
        }
        
        $pattern = config('tenant.security.tenant_id_pattern', '/^[a-zA-Z0-9_-]{1,64}$/');
        if (!preg_match($pattern, (string)$tenantId)) {
            throw new TenantException('Invalid tenant ID format');
        }
    }
    
    /**
     * 获取订阅信息
     * 
     * @return array|null
     */
    public static function getSubscription(): ?array
    {
        $tenantInfo = self::getTenantInfo();
        return $tenantInfo['subscription'] ?? null;
    }
    
    /**
     * 获取租户隔离列名
     * 
     * @return string
     */
    public static function getTenantColumn(): string
    {
        return config('tenant.field_isolation.tenant_column', 'tenant_id');
    }
    
    /**
     * 检查是否为 SINGLE（非租户）模式
     * 
     * @return bool
     */
    public static function isSingleMode(): bool
    {
        return config('tenant.mode', 'single') === 'single';
    }

    /**
     * 检查租户功能是否启用
     * 
     * @return bool
     */
    public static function isTenantEnabled(): bool
    {
        if (self::isSingleMode()) {
            return false;
        }
        return config('tenant.enabled', true);
    }

    /**
     * 检查租户上下文是否已初始化
     * 
     * @return bool
     */
    public static function isInitialized(): bool
    {
        if (self::isSingleMode()) {
            return false;
        }
        return self::$tenantId !== null;
    }

    /**
     * 是否为超级管理员
     * (兼容别名)
     * 
     * @return bool
     */
    public static function isSuperAdmin(): bool
    {
        return self::$isAdmin;
    }

    /**
     * 获取数据库连接名
     *
     * @return string|null
     */
    public static function getConnectionName(): ?string
    {
        $mode = self::getIsolationMode();
        if ($mode === 'database' && self::$tenantId !== null) {
            return 'tenant_' . self::$tenantId;
        }

        return null;
    }

    /**
     * 检查订阅是否有效
     * 
     * @return bool
     */
    public static function isSubscriptionValid(): bool
    {
        $subscription = self::getSubscription();
        
        if (!$subscription) {
            return false;
        }
        
        $now = time();
        $expireTime = strtotime($subscription['expire_time'] ?? '');
        
        // 检查是否过期
        if ($expireTime && $now > $expireTime) {
            $gracePeriod = config('tenant.subscription.grace_period', 7) * 86400;
            if ($now > $expireTime + $gracePeriod) {
                return false;
            }
        }
        
        // 检查状态
        return in_array($subscription['status'] ?? '', ['active', 'trial']);
    }
    
    /**
     * 检查是否在宽限期内
     * 
     * @return bool
     */
    public static function isInGracePeriod(): bool
    {
        $subscription = self::getSubscription();
        
        if (!$subscription) {
            return false;
        }
        
        $now = time();
        $expireTime = strtotime($subscription['expire_time'] ?? '');
        
        if ($expireTime && $now > $expireTime) {
            $gracePeriod = config('tenant.subscription.grace_period', 7) * 86400;
            return $now <= $expireTime + $gracePeriod;
        }
        
        return false;
    }
    
    /**
     * 魔术方法：支持静态调用
     */
    public static function __callStatic($name, $arguments)
    {
        // 兼容旧版本的 API
        if (str_starts_with($name, 'get')) {
            $key = strtolower(substr($name, 3));
            return self::getData($key, $arguments[0] ?? null);
        }
        
        if (str_starts_with($name, 'set')) {
            $key = strtolower(substr($name, 3));
            self::setData($key, $arguments[0] ?? null);
            return;
        }
        
        throw new TenantException("Method {$name} does not exist");
    }
}
