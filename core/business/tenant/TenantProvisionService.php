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
namespace core\business\tenant;

use app\dao\tenant\TenantDao;
use app\dao\tenant\TenantMigrationDao;
use app\model\tenant\Tenant;
use app\service\admin\system\MenuService;
use app\service\platform\tenant\TenantSyncService;
use core\foundation\base\BaseService;
use core\infrastructure\cache\CacheService;
use core\foundation\exception\handler\TenantException;
use core\business\tenant\context\TenantContext;
use core\io\uuid\Snowflake;
use Illuminate\Support\Facades\Log;
use support\Container;

/**
 * 租户初始化服务
 * 
 * 负责租户的创建、初始化、删除等操作
 * 使用 Laravel ORM 写法
 */
class TenantProvisionService extends BaseService
{
    /**
     * 迁移路径
     * @var string
     */
    protected string $migrationPath;
    
    /**
     * 种子数据路径
     * @var string
     */
    protected string $seederPath;

    /**
     * 缓存服务
     * @var CacheService
     */
    protected CacheService $cache;

    /**
     * 租户迁移 DAO
     * @var TenantMigrationDao
     */
    protected TenantMigrationDao $tenantMigrationDao;

    /**
     * 构造函数
     *
     * @param TenantDao $dao
     * @param CacheService $cache
     */
    public function __construct(TenantDao $dao, CacheService $cache)
    {
        $this->dao = $dao;
        $this->cache = $cache;
        $this->tenantMigrationDao = Container::make(TenantMigrationDao::class);
        $this->migrationPath = config('tenant.database_isolation.migration_path', 'database/tenant_migrations');
        $this->seederPath = config('tenant.database_isolation.seeder_path', 'database/tenant_seeders');
    }

    /**
     * 创建租户
     *
     * @param array $data
     * @param bool  $createDatabase
     *
     * @return int|string
     * @throws \core\foundation\exception\handler\TenantException
     */
    public function createTenant(array $data, bool $createDatabase = false): int|string
    {
        $this->validateTenantData($data);
        
        // 创建租户记录
        $tenant = $this->createTenantRecord($data);
        $tenantId = $tenant->id;

        // 如果需要创建独立数据库
        if ($createDatabase) {
            $this->provisionTenantDatabase($tenantId);
        }

        // 初始化租户数据
        $this->initializeTenantData($tenantId, $data);

        // 同步模板菜单（FIELD/DB 模式从 saas_template_menu 复制菜单树）
        $this->syncTenantMenus($tenantId, $tenant);

        // 同步模板字典（FIELD/DB 模式从 saas_template_dict 复制字典）
        $this->syncTenantDicts($tenantId, $tenant);

        // 清除租户缓存
        $this->clearTenantCache($tenantId);
        
        Log::info("Tenant created: id={$tenantId}, name={$data['name']}");

        return $tenantId;
    }

    /**
     * 验证租户数据
     *
     * @param array $data
     *
     * @return void
     * @throws \core\foundation\exception\handler\TenantException
     */
    protected function validateTenantData(array $data): void
    {
        $requiredFields = ['name', 'code'];

        foreach ($requiredFields as $field) {
            if (empty($data[$field])) {
                throw new TenantException("Missing required field: {$field}");
            }
        }

        // 验证租户编码格式
        $pattern = config('tenant.security.tenant_id_pattern', '/^[a-zA-Z0-9_-]{1,64}$/');
        if (!preg_match($pattern, $data['code'])) {
            throw new TenantException('Invalid tenant code format');
        }

        // 检查租户编码是否已存在
        if ($this->tenantExists($data['code'])) {
            throw new TenantException('Tenant code already exists');
        }
    }

    /**
     * 检查租户是否存在
     *
     * @param string $code
     * @return bool
     */
    protected function tenantExists(string $code): bool
    {
        // 使用 withoutGlobalScopes 避免租户作用域影响
        return Tenant::withoutGlobalScopes()
            ->where('code', $code)
            ->exists();
    }

    /**
     * 创建租户记录
     *
     * @param array $data
     * @return Tenant
     */
    protected function createTenantRecord(array $data): Tenant
    {
        $tenantData = [
            'name'            => $data['name'],
            'code'            => $data['code'],
            'status'          => $data['status'] ?? Tenant::STATUS_ACTIVE,
            'effective_mode'  => $data['effective_mode'] ?? Tenant::EFFECTIVE_IMMEDIATE,
            'start_time'      => $data['start_time'] ?? null,
            'database_mode'   => $data['database_mode'] ?? Tenant::MODE_FIELD,
            'db_setting_id'   => $data['db_setting_id'] ?? null,
            'domain'          => $data['domain'] ?? null,
            'contact_name'    => $data['contact_name'] ?? null,
            'contact_phone'   => $data['contact_phone'] ?? null,
            'contact_email'   => $data['contact_email'] ?? null,
            'contact_address' => $data['contact_address'] ?? null,
            'system_name'     => $data['system_name'] ?? null,
            'subscription_id' => $data['subscription_id'] ?? null,
            'expire_time'     => $data['expire_time'] ?? null,
            'sort'            => $data['sort'] ?? 0,
            'settings'        => $data['settings'] ?? [],
        ];

        // 使用 Laravel ORM 的 create 方法
        return Tenant::withoutGlobalScopes()->create($tenantData);
    }

    /**
     * 初始化租户数据库
     *
     * @param int|string $tenantId
     *
     * @return void
     * @throws \core\foundation\exception\handler\TenantException
     */
    public function provisionTenantDatabase(int|string $tenantId): void
    {
        TenantConnectionManager::ensureDatabaseExists($tenantId);
        $this->runTenantMigrations($tenantId);
        $this->recordTenantDatabaseConfig($tenantId);
    }

    /**
     * 执行租户迁移
     *
     * @param int|string $tenantId
     *
     * @return void
     * @throws \core\foundation\exception\handler\TenantException
     */
    protected function runTenantMigrations(int|string $tenantId): void
    {
        TenantConnectionManager::setCurrentConnection($tenantId, false);

        try {
            $migrations = $this->getMigrationFiles();
            foreach ($migrations as $migration) {
                $this->executeMigration($tenantId, $migration);
            }
        } finally {
            TenantConnectionManager::releaseCurrentConnection();
        }
    }

    /**
     * 获取迁移文件列表
     *
     * @return array
     */
    protected function getMigrationFiles(): array
    {
        $path = base_path() . '/' . $this->migrationPath;

        if (!is_dir($path)) {
            return [];
        }

        $files = glob($path . '/*.php');

        return array_map(function ($file) {
            return basename($file, '.php');
        }, $files);
    }

    /**
     * 执行迁移
     *
     * @param int|string $tenantId
     * @param string     $migration
     *
     * @return void
     * @throws \core\foundation\exception\handler\TenantException
     */
    protected function executeMigration(int|string $tenantId, string $migration): void
    {
        $path = base_path() . '/' . $this->migrationPath . '/' . $migration . '.php';

        if (!file_exists($path)) {
            return;
        }

        TenantConnectionManager::setCurrentConnection($tenantId, false);

        try {
            $instance = require $path;

            // 支持两种格式:
            // 1. 匿名类: return new class { public function up(Builder $schema) { ... } };
            // 2. 命名类: return 'ClassName'; 或传统 require_once + class_exists
            // 主迁移文件使用 Builder $schema 参数，所以统一传入 schema builder
            $schema = \Illuminate\Support\Facades\DB::connection()->getSchemaBuilder();

            if (is_object($instance) && method_exists($instance, 'up')) {
                $instance->up($schema);
                $this->recordMigration($tenantId, $migration);
                return;
            }

            // 传统命名类格式（兼容）
            if (is_string($instance)) {
                $className = $instance;
            } elseif (is_array($instance) && isset($instance['class'])) {
                $className = $instance['class'];
            } else {
                $className = $this->loadMigrationClass($migration);
            }

            if ($className && class_exists($className) && method_exists($className, 'up')) {
                (new $className())->up($schema);
                $this->recordMigration($tenantId, $migration);
            }
        } finally {
            TenantConnectionManager::releaseCurrentConnection();
        }
    }

    /**
     * 加载迁移类
     *
     * @param string $migration
     * @return string|null
     */
    protected function loadMigrationClass(string $migration): ?string
    {
        $path = base_path() . $this->migrationPath . '/' . $migration . '.php';

        if (!file_exists($path)) {
            return null;
        }

        require_once $path;

        // 尝试多种类名格式
        $classNames = [
            'tenant\\migrations\\' . $migration,
            'TenantMigration' . str_replace('_', '', ucwords($migration, '_')),
            'Tenant_' . str_replace('_', '', ucwords($migration, '_')),
        ];

        foreach ($classNames as $className) {
            if (class_exists($className)) {
                return $className;
            }
        }

        return null;
    }

    /**
     * 记录已执行的迁移（写入 sys_tenant_migration 表）
     *
     * @param int|string $tenantId
     * @param string $migration
     * @return void
     */
    protected function recordMigration(int|string $tenantId, string $migration): void
    {
        $now = time();
        $this->tenantMigrationDao->save([
            'id' => Snowflake::generate(),
            'tenant_id' => $tenantId,
            'type' => 'migrate',
            'target' => $migration,
            'batch' => 1,
            'status' => 'success',
            'started_at' => $now,
            'finished_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /**
     * 初始化租户数据
     *
     * @param int|string $tenantId
     * @param array $data
     * @return void
     */
    protected function initializeTenantData(int|string $tenantId, array $data): void
    {
        TenantContext::setTenant($tenantId);

        try {
            $this->runTenantSeeders($tenantId, $data);
            $this->createDefaultConfigs($tenantId);
            $this->createDefaultWebMenus($tenantId);
        } finally {
            TenantContext::clear();
        }
    }

    /**
     * 执行租户种子数据
     *
     * @param int|string $tenantId
     * @param array $data
     * @return void
     */
    protected function runTenantSeeders(int|string $tenantId, array $data): void
    {
        $seeders = $this->getSeederFiles();

        foreach ($seeders as $seeder) {
            $this->executeSeeder($tenantId, $seeder, $data);
        }
    }

    /**
     * 获取种子文件列表
     *
     * @return array
     */
    protected function getSeederFiles(): array
    {
        $path = base_path() . '/' . $this->seederPath;

        if (!is_dir($path)) {
            return [];
        }

        $files = glob($path . '/*.php');

        return array_map(function ($file) {
            return basename($file, '.php');
        }, $files);
    }

    /**
     * 执行种子
     *
     * @param int|string $tenantId
     * @param string $seeder
     * @param array $data
     * @return void
     */
    protected function executeSeeder(int|string $tenantId, string $seeder, array $data): void
    {
        $path = base_path() . '/' . $this->seederPath . '/' . $seeder . '.php';

        if (!file_exists($path)) {
            return;
        }

        require_once $path;

        $className = 'tenant\\seeders\\' . $seeder;
        if (!class_exists($className)) {
            return;
        }

        $seederClass = new $className();

        if (method_exists($seederClass, 'run')) {
            $seederClass->run($tenantId, $data);
        }
    }

    /**
     * 创建默认配置
     *
     * @param int|string $tenantId
     * @return void
     */
    protected function createDefaultConfigs(int|string $tenantId): void
    {
        $this->createDefaultSystemConfig($tenantId);
        $this->createDefaultPermissionConfig($tenantId);
    }

    /**
     * 创建默认系统配置：从配置模板同步到租户
     *
     * @param int|string $tenantId
     * @return void
     */
    protected function createDefaultSystemConfig(int|string $tenantId): void
    {
        $tenant = Tenant::withoutGlobalScopes()->find($tenantId);
        if (!$tenant) {
            Log::warning("createDefaultSystemConfig: tenant not found, id={$tenantId}");
            return;
        }

        $mode = $tenant->database_mode ?? 'field';

        // single 模式跳过配置同步（单库模式不走模板）
        if ($mode === 'single') {
            return;
        }

        try {
            /** @var TenantSyncService $syncService */
            $syncService = Container::make(TenantSyncService::class);
            $result = $syncService->syncConfigData((int) $tenantId, $mode);

            Log::info("Tenant configs synced: tenant_id={$tenantId}, mode={$mode}, count={$result['count']}");
        } catch (\Throwable $e) {
            Log::error("Tenant config sync failed: tenant_id={$tenantId}, mode={$mode}, error={$e->getMessage()}");
        }
    }

    /**
     * 创建默认前端菜单：从前端菜单模板同步到租户
     *
     * @param int|string $tenantId
     * @return void
     */
    protected function createDefaultWebMenus(int|string $tenantId): void
    {
        $tenant = Tenant::withoutGlobalScopes()->find($tenantId);
        if (!$tenant) {
            Log::warning("createDefaultWebMenus: tenant not found, id={$tenantId}");
            return;
        }

        $mode = $tenant->database_mode ?? 'field';

        // single 模式跳过前端菜单同步（单库模式不走模板）
        if ($mode === 'single') {
            return;
        }

        try {
            /** @var TenantSyncService $syncService */
            $syncService = Container::make(TenantSyncService::class);
            $result = $syncService->syncWebMenuData((int) $tenantId, $mode);

            Log::info("Tenant web menus synced: tenant_id={$tenantId}, mode={$mode}, count={$result['count']}");
        } catch (\Throwable $e) {
            Log::error("Tenant web menu sync failed: tenant_id={$tenantId}, mode={$mode}, error={$e->getMessage()}");
        }
    }

    /**
     * 创建默认权限配置
     *
     * @param int|string $tenantId
     * @return void
     */
    protected function createDefaultPermissionConfig(int|string $tenantId): void {}

    /**
     * 同步模板菜单到该租户
     *
     * 根据租户的 database_mode 模式：
     * - field 模式：复制到 sys_menu（设置 tenant_id）
     * - database 模式：复制到租户独立库的 sys_tenant_menu
     * - single 模式：跳过（不涉及租户概念）
     *
     * @param int|string $tenantId
     * @param Tenant     $tenant
     * @return void
     */
    protected function syncTenantMenus(int|string $tenantId, Tenant $tenant): void
    {
        $mode = $tenant->database_mode ?? Tenant::MODE_FIELD;

        // SINGLE 模式跳过菜单同步
        if ($mode === 'single') {
            return;
        }

        try {
            /** @var MenuService $menuService */
            $menuService = Container::make(MenuService::class);
            $result = $menuService->syncTemplateMenus((int) $tenantId, $mode);

            Log::info("Tenant menus synced: tenant_id={$tenantId}, mode={$mode}, count={$result['count']}");
        } catch (\Throwable $e) {
            Log::error("Tenant menu sync failed: tenant_id={$tenantId}, mode={$mode}, error={$e->getMessage()}");
            // 菜单同步失败不应该阻止租户创建，记录日志即可
        }
    }

    /**
     * 同步模板字典到该租户
     *
     * 根据租户的 database_mode 模式：
     * - field 模式：复制到 sys_dict（设置 tenant_id）
     * - database 模式：复制到租户独立库的 sys_dict
     * - single 模式：跳过（不涉及租户概念）
     *
     * @param int|string $tenantId
     * @param Tenant     $tenant
     * @return void
     */
    protected function syncTenantDicts(int|string $tenantId, Tenant $tenant): void
    {
        $mode = $tenant->database_mode ?? Tenant::MODE_FIELD;

        // SINGLE 模式跳过字典同步
        if ($mode === 'single') {
            return;
        }

        try {
            /** @var \app\service\admin\system\dict\DictService $dictService */
            $dictService = Container::make(\app\service\admin\system\dict\DictService::class);
            $result = $dictService->syncTemplateDicts((int) $tenantId, $mode);

            Log::info("Tenant dicts synced: tenant_id={$tenantId}, mode={$mode}, count={$result['count']}");
        } catch (\Throwable $e) {
            Log::error("Tenant dict sync failed: tenant_id={$tenantId}, mode={$mode}, error={$e->getMessage()}");
            // 字典同步失败不应该阻止租户创建，记录日志即可
        }
    }

    /**
     * 记录租户数据库配置
     *
     * @param int|string $tenantId
     * @return void
     */
    protected function recordTenantDatabaseConfig(int|string $tenantId): void
    {
        $tenant = Tenant::withoutGlobalScopes()->find($tenantId);

        if ($tenant) {
            $tenant->database_name = TenantConnectionManager::getTenantDatabaseName($tenantId);
            $tenant->save();
        }
    }

    /**
     * 删除租户
     *
     * @param int|string $tenantId
     * @param bool $deleteDatabase
     * @return void
     */
    public function deleteTenant(int|string $tenantId, bool $deleteDatabase = true): void
    {
        $tenant = Tenant::withoutGlobalScopes()->find($tenantId);

        if (!$tenant) {
            throw new TenantException('Tenant not found');
        }

        if ($this->hasAssociatedData($tenantId)) {
            throw new TenantException('Tenant has associated data, please delete data first');
        }

        if ($deleteDatabase && $tenant->database_mode === Tenant::MODE_DATABASE) {
            TenantConnectionManager::deleteDatabase($tenantId);
        }

        $tenant->delete();
        $this->clearTenantCache($tenantId);
        
        Log::info("Tenant deleted: id={$tenantId}");
    }

    /**
     * 检查是否有关联数据
     *
     * @param int|string $tenantId
     * @return bool
     */
    protected function hasAssociatedData(int|string $tenantId): bool { return false; }

    /**
     * 更新租户
     *
     * @param int|string $tenantId
     * @param array $data
     * @return void
     */
    public function updateTenant(int|string $tenantId, array $data): void
    {
        $tenant = Tenant::withoutGlobalScopes()->find($tenantId);

        if (!$tenant) {
            throw new TenantException('Tenant not found');
        }

        $allowedFields = [
            'name', 'code', 'status', 'effective_mode', 'start_time',
            'database_mode', 'db_setting_id', 'domain',
            'contact_name', 'contact_phone', 'contact_email', 'contact_address',
            'system_name', 'subscription_id', 'expire_time', 'sort',
            'settings', 'suspend_reason', 'suspend_time',
        ];

        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $tenant->$field = $data[$field];
            }
        }

        $tenant->save();
        $this->clearTenantCache($tenantId);
        
        Log::info("Tenant updated: id={$tenantId}");
    }

    /**
     * 暂停租户
     *
     * @param int|string $tenantId
     * @param string $reason
     * @return void
     */
    public function suspendTenant(int|string $tenantId, string $reason = ''): void
    {
        $this->updateTenant($tenantId, [
            'status' => Tenant::STATUS_SUSPENDED,
            'suspend_reason' => $reason,
            'suspend_time' => now(),
        ]);
    }

    /**
     * 激活租户
     *
     * @param int|string $tenantId
     * @return void
     */
    public function activateTenant(int|string $tenantId): void
    {
        $this->updateTenant($tenantId, [
            'status' => Tenant::STATUS_ACTIVE,
            'suspend_reason' => null,
            'suspend_time' => null,
        ]);
    }

    /**
     * 升级租户数据库
     *
     * @param int|string $tenantId
     * @return void
     */
    public function upgradeTenantDatabase(int|string $tenantId): void
    {
        TenantConnectionManager::setCurrentConnection($tenantId, false);

        try {
            $pendingMigrations = $this->getPendingMigrations($tenantId);
            foreach ($pendingMigrations as $migration) {
                $this->executeMigration($tenantId, $migration);
            }
        } finally {
            TenantConnectionManager::releaseCurrentConnection();
        }
    }

    /**
     * 获取待执行的迁移（对比 sys_tenant_migration 表）
     *
     * @param int|string $tenantId
     * @return array
     */
    protected function getPendingMigrations(int|string $tenantId): array
    {
        $allMigrations = $this->getMigrationFiles();
        $records = $this->tenantMigrationDao->getList([
            'tenant_id' => $tenantId,
            'type' => 'migrate',
            'status' => 'success',
        ], 'target', 0, 0, 'id asc');
        $executedMigrations = array_column($records, 'target');

        return array_diff($allMigrations, $executedMigrations);
    }

    /**
     * 清除租户缓存
     *
     * @param int|string $tenantId
     * @return void
     */
    protected function clearTenantCache(int|string $tenantId): void
    {
        $cacheKeys = [
            config('tenant.cache.tenant_config_key', 'tenant:config:{tenant_id}'),
            config('tenant.cache.subscription_key', 'tenant:subscription:{tenant_id}'),
        ];

        foreach ($cacheKeys as $key) {
            $cacheKey = str_replace('{tenant_id}', (string) $tenantId, $key);
            $this->cache->delete($cacheKey);
        }
    }

    /**
     * 获取租户列表
     *
     * @param array $filters
     * @param int $page
     * @param int $pageSize
     * @return array
     */
    public function getTenantList(array $filters = [], int $page = 1, int $pageSize = 20): array
    {
        $where = [];

        if (isset($filters['status'])) {
            $where['status'] = $filters['status'];
        }

        if (isset($filters['keyword'])) {
            $where[] = ['name', 'like', '%' . $filters['keyword'] . '%'];
        }

        if (isset($filters['expire_before'])) {
            $where[] = ['expire_time', '<=', $filters['expire_before']];
        }

        $total = $this->dao->count($where);
        $list = $this->dao->getList($where, '*', $page, $pageSize, 'id desc');

        return [
            'total' => $total,
            'list' => $list,
            'page' => $page,
            'page_size' => $pageSize,
        ];
    }

    /**
     * 获取租户详情
     *
     * @param int|string $tenantId
     * @return array|null
     */
    public function getTenantDetail(int|string $tenantId): ?array
    {
        $tenant = Tenant::withoutGlobalScopes()->find($tenantId);

        if (!$tenant) {
            return null;
        }

        $detail = $tenant->toArray();

        if ($detail['subscription_id']) {
            $detail['subscription'] = $this->getSubscriptionInfo($detail['subscription_id']);
        }

        if ($detail['database_mode'] === Tenant::MODE_DATABASE) {
            $detail['database_exists'] = TenantConnectionManager::databaseExists(
                TenantConnectionManager::getTenantDatabaseName($tenantId)
            );
        }

        return $detail;
    }

    /**
     * 获取订阅信息
     *
     * @param int|string $subscriptionId
     * @return array|null
     */
    protected function getSubscriptionInfo(int|string $subscriptionId): ?array { return null; }
}
