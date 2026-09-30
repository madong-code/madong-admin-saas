<?php

/**
 * 多租户配置文件
 * 
 * 文档位置: docs/saas/03-配置说明.md
 */

return [

    // ============================================
    // 运行模式配置
    // ============================================
    
    /**
     * 系统运行模式
     * 通过 .env 文件 APP_TENANT_ENABLED 配置，控制整个系统的多租户策略
     */
    'enable' => env('APP_TENANT_ENABLED', false),

    /**
     * 租户隔离模式
     *   - single:  单租户模式（不隔离）
     *   - field:   字段隔离（共享表，通过 tenant_id 字段区分）
     *   - database: 库隔离（每个租户独立数据库）
     * enable=true 时自动启用 field 模式，否则为 single
     */
    'mode' => env('APP_TENANT_ENABLED', false) ? 'field' : 'single',

    // ============================================
    // 模型配置
    // ============================================
    
    'models' => [
        // 租户模型
        'tenant' => \app\model\tenant\Tenant::class,
        
        // 订阅模型
        'subscription' => \app\model\tenant\Subscription::class,
        
        // 权限定义模型
        'permission' => \app\model\tenant\Permission::class,
        
        // 套餐-权限关联模型
        'subscription_permission' => \app\model\tenant\SubscriptionPermission::class,
        
        // 管理员-租户关联已废弃，通过 sys_admin.tenant_id 直接关联
    ],

    // ============================================
    // 隔离模式配置（FIELD/DB 模式适用）
    // ============================================
    
    /**
     * 默认隔离模式（新建租户时的默认隔离策略）
     * 仅当 mode 为 field 或 database 时生效
     * 可选值:
     *   - field: 字段隔离 (所有租户共用一张表，通过 tenant_id 字段区分)
     *   - database: 库隔离 (每个租户独立数据库)
     */
    'default_mode' => 'field',

    /**
     * 模块隔离模式映射
     * 格式: 'module_name' => 'isolation_mode'
     */
    'module_modes' => [
        'system_config' => 'field',
        'business' => 'field',
        'sensitive' => 'database',
        'customer' => 'field',
    ],

    // ============================================
    // 字段隔离配置
    // ============================================
    
    'field_isolation' => [
        // 默认租户标识字段名
        'tenant_column' => 'tenant_id',
        
        // 管理员是否跳过字段作用域（默认否，管理员也需租户隔离）
        'admin_bypass' => false,
        
        // 跨租户查询白名单 (不自动添加租户过滤的模型)
        'whitelist' => [
            // // SystemConfig::class,
            // \app\model\system\admin\Admin::class,
            // // Web 前端公共数据模型（不需要租户隔离）
            // \app\model\web\Adv::class,
            // \app\model\web\Link::class,
            // \app\model\web\Menu::class,
        ],
        
        // 全局自动注入租户ID
        'auto_inject' => true,
    ],

    // ============================================
    // 库隔离配置
    // ============================================
    
    'database_isolation' => [
        // 租户数据库连接配置模板（复用主库连接的 host/port/user/password，仅替换 database 名称）
        // 显式指定时以此为准；留空（null）则动态跟随 config('database.default')
        'connection_template' => null,
        
        // 数据库前缀模板 {tenant_id} 会替换为实际租户ID
        'database_pattern' => 'saas_tenant_{tenant_id}',
        
        // 连接池配置
        'pool' => [
            'min_connections' => 1,
            'max_connections' => 10,
            'wait_timeout' => 30,
        ],
        
        // 租户数据库初始化脚本（共用主库迁移文件，统一维护一份）
        'migration_path' => 'resource/database/migrations',
    ],

    // ============================================
    // 功能订阅配置
    // ============================================
    
    'subscription' => [
        // 是否启用功能订阅
        'enabled' => true,
        
        // 订阅过期检查周期 (小时)
        'check_interval' => 24,
        
        // 宽限期 (天) - 订阅过期后仍可使用的天数
        'grace_period' => 7,
        
        // 权限策略表
        'permission_table' => 'saas_subscription_permission',
    ],

    // ============================================
    // 缓存配置
    // ============================================
    
    'cache' => [
        // 租户配置缓存 Key
        'tenant_config_key' => 'tenant:config:{tenant_id}',
        
        // 租户订阅信息缓存 Key
        'subscription_key' => 'tenant:subscription:{tenant_id}',
        
        // 缓存 TTL (秒)
        'ttl' => 3600,
        
        // 缓存驱动
        'driver' => 'redis',
    ],

    // ============================================
    // 平台配置
    // ============================================
    
    'platform' => [
        // 平台 API 地址 (用于租户管理)
        'api_url' => env('TENANT_PLATFORM_API_URL', 'http://platform.madong.local/api'),
        
        // 平台 API Key
        'api_key' => env('TENANT_PLATFORM_API_KEY', ''),
        
        // 平台 API Secret
        'api_secret' => env('TENANT_PLATFORM_API_SECRET', ''),
        
        // 请求超时 (秒)
        'timeout' => 30,
        
        // 是否启用本地缓存 (减少平台 API 调用)
        'local_cache' => true,
    ],

    // ============================================
    // 日志配置
    // ============================================
    
    'logging' => [
        // 是否记录租户切换日志
        'enabled' => true,
        
        // 日志级别
        'level' => 'debug',
        
        // 敏感操作日志
        'sensitive_operations' => [
            'create_tenant',
            'delete_tenant',
            'change_subscription',
            'cross_tenant_access',
        ],
    ],

    // ============================================
    // 安全配置
    // ============================================
    
    'security' => [
        // 是否启用租户数据校验
        'validate_tenant_data' => true,
        
        // 跨租户访问限制
        'cross_tenant_access' => [
            // 是否允许跨租户查询
            'allow_query' => false,
            
            // 是否允许跨租户写入
            'allow_write' => false,
        ],
        
        // 租户 ID 验证规则
        'tenant_id_pattern' => '/^[a-zA-Z0-9_-]{1,64}$/',
    ],

    // ============================================
    // 特性开关
    // ============================================
    
    'features' => [
        // 是否启用多域名
        'multi_domain' => true,
        
        // 是否启用多端点
        'multi_endpoint' => true,
        
        // 是否启用白名单 IP
        'ip_whitelist' => true,
        
        // 是否启用操作审计
        'audit_log' => true,
        
        // 是否启用数据导出
        'data_export' => true,
    ],

];
