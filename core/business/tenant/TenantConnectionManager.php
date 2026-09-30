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

use app\model\tenant\Tenant;
use core\foundation\exception\handler\TenantException;
use core\business\tenant\context\TenantContext;
use Illuminate\Container\Container;
use support\Context;
use support\Db as DB;

/**
 * 租户连接管理器
 * 
 * 负责管理库隔离模式下的租户数据库连接
 * 实现连接的创建、获取、释放等
 * 使用 Laravel ORM 连接方式
 *
 * 协程安全说明：
 *   连接配置模板（init() 设定的连接模板/库名模式/前缀）与动态注册到容器 config 的
 *   连接定义属于「进程级共享配置」，跨请求复用，保留静态；
 *   「当前请求所在租户连接」属于请求态，存放于 support\Context（键 tenant.connection），
 *   协程模式下按协程隔离，避免并发串号。
 *   类名 / 命名空间 / 文件路径 / 公开方法签名保持不变，调用方无需改动。
 */
class TenantConnectionManager
{
    /**
     * 连接配置（进程级共享，跨请求复用）
     * @var array
     */
    protected static array $config = [];

    /**
     * 上下文键：当前请求的租户连接名
     */
    private const CTX_CONNECTION = 'tenant.connection';

    /**
     * 初始化
     *
     * @param array $config
     * @return void
     */
    public static function init(array $config = []): void
    {
        self::$config = array_merge([
            'connection_template' => 'mysql',
            'database_pattern' => 'saas_tenant_{tenant_id}',
            'prefix' => env('DB_PREFIX', 'md_'),
        ], $config);
    }

    /**
     * 获取租户数据库连接
     *
     * @param int|string $tenantId
     * @param bool       $autoCreate 是否自动创建数据库
     *
     * @return \Illuminate\Database\Connection
     * @throws \core\foundation\exception\handler\TenantException
     */
    public static function getConnection(int|string $tenantId, bool $autoCreate = false): \Illuminate\Database\Connection
    {
        // SINGLE 模式下返回默认连接
        if (config('tenant.mode', 'single') === 'single') {
            return DB::connection();
        }

        $connectionName = self::getConnectionName($tenantId);
        $targetDatabase = self::getTenantDatabaseName($tenantId);

        // 先检查容器 config 中是否已有该连接的注册配置
        // 注意：此步骤只读 config 数组，不调 DB::connection()，避免配置不存在时
        // Laravel DatabaseManager 用空配置尝试创建 PDO 导致 MySQL 拒绝连接
        $container = Container::getInstance();
        $connections = $container['config']['database.connections'] ?? [];

        if (isset($connections[$connectionName])) {
            $existingDb = $connections[$connectionName]['database'] ?? null;
            if ($existingDb === $targetDatabase) {
                // 配置数据库名一致 → 直接复用现有连接
                // 死连接检测交给 Pool 框架的心跳机制（heartbeat_interval + idle_timeout）
                return DB::connection($connectionName);
            }
            // 数据库名变了 → 清除旧配置，后续重建
            DB::purge($connectionName);
            unset($connections[$connectionName]);
            $container['config']['database.connections'] = $connections;
        }

        // 构建新的连接配置
        $config = self::buildConnectionConfig($tenantId);

        // 确保数据库存在
        if ($autoCreate) {
            self::ensureDatabaseExists($tenantId);
        }

        // 动态添加连接（通过 Illuminate 容器 config 注册，DB::connection() 自动读取创建）
        $connections[$connectionName] = $config;
        $container['config']['database.connections'] = $connections;

        return DB::connection($connectionName);
    }

    /**
     * 设置当前租户连接
     *
     * @param int|string $tenantId
     * @param bool       $autoCreate 是否自动创建
     *
     * @return \Illuminate\Database\Connection
     * @throws \core\foundation\exception\handler\TenantException
     */
    public static function setCurrentConnection(int|string $tenantId, bool $autoCreate = false): \Illuminate\Database\Connection
    {
        // 先设置租户上下文，确保 getConnection/buildConnectionConfig 能获取到
        // 完整的租户信息（database_name / db_setting 等自定义数据库配置）
        TenantContext::setTenant($tenantId);
        TenantContext::setIsolationMode('database');

        $connection = self::getConnection($tenantId, $autoCreate);

        // 记录当前请求所在租户连接（请求态，存放于协程上下文）
        Context::set(self::CTX_CONNECTION, self::getConnectionName($tenantId));

        return $connection;
    }

    /**
     * 获取当前连接
     *
     * @return \Illuminate\Database\Connection|null
     */
    public static function getCurrentConnection(): ?\Illuminate\Database\Connection
    {
        $connectionName = Context::get(self::CTX_CONNECTION);
        try {
            return $connectionName ? DB::connection($connectionName) : DB::connection();
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * 释放当前连接
     *
     * Laravel 连接由连接池统一管理，无需显式释放，仅清理请求态记录。
     *
     * @return void
     */
    public static function releaseCurrentConnection(): void
    {
        Context::set(self::CTX_CONNECTION, null);
    }

    /**
     * 获取连接名称
     *
     * @param int|string $tenantId
     * @return string
     */
    protected static function getConnectionName(int|string $tenantId): string
    {
        return 'tenant_' . $tenantId;
    }

    /**
     * 获取配置项（支持从 tenant.php 配置自动加载）
     *
     * @param string $key
     * @param mixed  $default
     * @return mixed
     */
    protected static function getConfig(string $key, mixed $default = null): mixed
    {
        // 优先使用 init() 设定的值
        if (isset(self::$config[$key])) {
            return self::$config[$key];
        }
        // 回退到 tenant.php 配置
        $dbConfig = config('tenant.database_isolation', []);
        return $dbConfig[$key] ?? $default;
    }

    /**
     * 获取默认连接名（主库连接名）
     *
     * 动态读取 config('database.default')，避免在代码中硬编码 'mysql'。
     * 当 database.default 配置变化时，租户连接的模板选择与兜底自动跟随。
     * tenant.database_isolation.connection_template 可显式覆盖此值。
     *
     * @return string
     */
    public static function getDefaultConnectionName(): string
    {
        // 优先 tenant 配置显式指定的模板，否则用框架默认连接名，最终兜底 'mysql'
        $explicit = self::getConfig('connection_template');
        if (!empty($explicit)) {
            return $explicit;
        }
        return config('database.default', 'mysql');
    }

    /**
     * 获取租户连接池配置
     *
     * 合并默认连接的 pool 配置（基础值：heartbeat_interval/idle_timeout 等）
     * 与 tenant.database_isolation.pool（覆盖值：max_connections/wait_timeout 等），
     * 保证动态创建的租户连接具备连接池管理。
     *
     * @return array
     */
    public static function getTenantPoolConfig(): array
    {
        $defaultConn = self::getDefaultConnectionName();
        $basePool    = config("database.connections.{$defaultConn}.pool", []);
        $tenantPool  = config('tenant.database_isolation.pool', []);
        return array_merge($basePool, $tenantPool);
    }

    /**
     * 构建连接配置
     *
     * 优先级：
     * 1. db_setting（saas_db_setting 自定义数据源）→ 完整 host/port/user/pass/database
     * 2. database_name 字段 → mysql 模板 + 自定义库名
     * 3. 模式生成 → mysql 模板 + 模式库名
     *
     * @param int|string $tenantId
     * @return array
     */
    protected static function buildConnectionConfig(int|string $tenantId): array
    {
        $tenantInfo = TenantContext::getTenantInfo();
        $dbSetting = $tenantInfo['db_setting'] ?? null;

        // 1. 优先使用 db_setting 自定义数据源（完整连接配置）
        if (!empty($dbSetting)) {
            return [
                'driver'    => $dbSetting['driver'] ?? 'mysql',
                'host'      => $dbSetting['host'] ?? '127.0.0.1',
                'port'      => (int)($dbSetting['port'] ?? 3306),
                'database'  => $dbSetting['database'] ?? '',
                'username'  => $dbSetting['username'] ?? 'root',
                'password'  => $dbSetting['password'] ?? '',
                'charset'   => $dbSetting['charset'] ?? 'utf8mb4',
                'collation' => $dbSetting['collation'] ?? 'utf8mb4_general_ci',
                'prefix'    => $dbSetting['prefix'] ?? '',
                'strict'    => true,
                'engine'    => 'InnoDB',
                'pool'      => self::getTenantPoolConfig(),
            ];
        }

        // 2. 模板模式：用默认连接模板 + 实际数据库名
        $templateName = self::getDefaultConnectionName();
        $templateConfig = config("database.connections.{$templateName}", []);
        $databaseName = self::getTenantDatabaseName($tenantId);
        $prefix = self::getConfig('prefix', env('DB_PREFIX', 'md_'));

        return array_merge($templateConfig, [
            'driver' => 'mysql',
            'host' => $templateConfig['host'] ?? '127.0.0.1',
            'port' => $templateConfig['port'] ?? 3306,
            'database' => $databaseName,
            'username' => $templateConfig['username'] ?? 'root',
            'password' => $templateConfig['password'] ?? '',
            'charset' => $templateConfig['charset'] ?? 'utf8mb4',
            'collation' => $templateConfig['collation'] ?? 'utf8mb4_unicode_ci',
            'prefix' => $prefix,
        ]);
    }

    /**
     * 清除租户连接配置
     *
     * @param int|string $tenantId
     * @return void
     */
    public static function removeConnection(int|string $tenantId): void
    {
        $connectionName = self::getConnectionName($tenantId);
        // 断开连接
        try {
            DB::connection($connectionName)->disconnect();
        } catch (\Throwable $e) {
            // 连接不存在则忽略
        }
        // 从容器 config 中移除
        try {
            $container = Container::getInstance();
            $connections = $container['config']['database.connections'] ?? [];
            unset($connections[$connectionName]);
            $container['config']['database.connections'] = $connections;
        } catch (\Throwable $e) {
            // 忽略
        }
    }

    /**
     * 清除所有租户连接
     *
     * @return void
     */
    public static function clearAllConnections(): void
    {
        try {
            $container = Container::getInstance();
            $connections = $container['config']['database.connections'] ?? [];
            foreach ($connections as $name => $config) {
                if (str_starts_with($name, 'tenant_')) {
                    try {
                        DB::connection($name)->disconnect();
                    } catch (\Throwable $e) {
                        // 连接不存在则忽略
                    }
                    unset($connections[$name]);
                }
            }
            $container['config']['database.connections'] = $connections;
        } catch (\Throwable $e) {
            // 忽略错误
        }
    }

    /**
     * 确保数据库存在
     *
     * @param int|string $tenantId
     *
     * @return bool
     * @throws \core\foundation\exception\handler\TenantException
     */
    public static function ensureDatabaseExists(int|string $tenantId): bool
    {
        $databaseName = self::getTenantDatabaseName($tenantId);

        if (self::databaseExists($databaseName)) {
            return true;
        }

        return self::createDatabase($databaseName);
    }

    /**
     * 检查数据库是否存在
     *
     * @param string $databaseName
     *
     * @return bool
     * @throws \core\foundation\exception\handler\TenantException
     */
    public static function databaseExists(string $databaseName): bool
    {
        try {
            // 使用主连接查询数据库列表
            $result = DB::select("SHOW DATABASES LIKE ?", [$databaseName]);
            return !empty($result);
        } catch (\Exception $e) {
            throw new TenantException('Failed to check database existence: ' . $e->getMessage());
        }
    }

    /**
     * 创建数据库
     *
     * @param string $databaseName
     *
     * @return bool
     * @throws \core\foundation\exception\handler\TenantException
     */
    public static function createDatabase(string $databaseName): bool
    {
        try {
            // 获取主连接配置（动态读取默认连接名）
            $config = config("database.connections." . self::getDefaultConnectionName(), []);
            
            // 使用 PDO 直接连接（不带数据库名）
            $dsn = sprintf(
                'mysql:host=%s;port=%s;charset=%s',
                $config['host'] ?? '127.0.0.1',
                $config['port'] ?? 3306,
                $config['charset'] ?? 'utf8mb4'
            );

            $pdo = new \PDO(
                $dsn,
                $config['username'] ?? 'root',
                $config['password'] ?? '',
                [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
            );

            $charset = $config['charset'] ?? 'utf8mb4';
            $sql = "CREATE DATABASE `{$databaseName}` CHARACTER SET {$charset} COLLATE {$charset}_unicode_ci";
            $pdo->exec($sql);

            return true;
        } catch (\PDOException $e) {
            throw new TenantException('Failed to create database: ' . $e->getMessage());
        }
    }

    /**
     * 删除数据库
     *
     * @param int|string $tenantId
     *
     * @return bool
     * @throws \core\foundation\exception\handler\TenantException
     */
    public static function deleteDatabase(int|string $tenantId): bool
    {
        $databaseName = self::getTenantDatabaseName($tenantId);

        try {
            // 获取主连接配置（动态读取默认连接名）
            $config = config("database.connections." . self::getDefaultConnectionName(), []);
            
            // 使用 PDO 直接连接
            $dsn = sprintf(
                'mysql:host=%s;port=%s;charset=%s',
                $config['host'] ?? '127.0.0.1',
                $config['port'] ?? 3306,
                $config['charset'] ?? 'utf8mb4'
            );

            $pdo = new \PDO(
                $dsn,
                $config['username'] ?? 'root',
                $config['password'] ?? '',
                [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
            );

            // 先关闭所有连接
            $pdo->exec("SELECT CONCAT('KILL CONNECTION ', id) FROM INFORMATION_SCHEMA.PROCESSLIST WHERE db = '{$databaseName}'");
            
            // 删除数据库
            $sql = "DROP DATABASE IF EXISTS `{$databaseName}`";
            $pdo->exec($sql);

            // 移除连接配置
            self::removeConnection($tenantId);

            return true;
        } catch (\PDOException $e) {
            throw new TenantException('Failed to delete database: ' . $e->getMessage());
        }
    }

    /**
     * 获取连接状态
     *
     * @return array
     */
    public static function getConnectionStatus(): array
    {
        $connections = config('database.connections', []);
        $tenantConnections = [];

        foreach ($connections as $name => $config) {
            if (str_starts_with($name, 'tenant_')) {
                $tenantConnections[] = $name;
            }
        }

        return [
            'tenant_connections' => count($tenantConnections),
            'connections' => $tenantConnections,
        ];
    }

    /**
     * 获取租户数据库配置
     *
     * @param int|string $tenantId
     * @return array
     */
    public static function getTenantDbConfig(int|string $tenantId): array
    {
        return self::buildConnectionConfig($tenantId);
    }

    /**
     * 获取租户数据库名
     *
     * @param int|string $tenantId
     * @return string
     */
    public static function getTenantDatabaseName(int|string $tenantId): string
    {
        $tenantInfo = TenantContext::getTenantInfo();

        // 1. 从 db_setting（saas_db_setting 关联数据源）获取自定义数据库名
        $dbSetting = $tenantInfo['db_setting'] ?? null;
        if (!empty($dbSetting['database'])) {
            return $dbSetting['database'];
        }

        // 2. 从租户表 database_name 字段获取
        if ($tenantInfo && !empty($tenantInfo['database_name'])) {
            return $tenantInfo['database_name'];
        }

        // 3. 回退到模式生成
        $pattern = self::getConfig('database_pattern', 'saas_tenant_{tenant_id}');
        return str_replace('{tenant_id}', (string) $tenantId, $pattern);
    }

    /**
     * 执行租户数据库迁移
     *
     * @param int|string $tenantId
     * @param array      $migrations 迁移文件列表
     *
     * @return void
     * @throws \core\foundation\exception\handler\TenantException
     */
    public static function runMigrations(int|string $tenantId, array $migrations): void
    {
        self::setCurrentConnection($tenantId, false);

        try {
            foreach ($migrations as $migration) {
                self::executeMigration($migration);
            }
        } finally {
            self::releaseCurrentConnection();
        }
    }

    /**
     * 执行单个迁移
     *
     * @param string $migration
     * @return void
     */
    protected static function executeMigration(string $migration): void
    {
        // 迁移执行逻辑
        $connection = DB::connection();
        $connection->getPdo()->exec($migration);
    }

    /**
     * 检查租户数据库是否可连接
     *
     * @param int|string $tenantId
     * @return bool
     */
    public static function isConnectable(int|string $tenantId): bool
    {
        try {
            $connection = self::getConnection($tenantId);
            $connection->getPdo();
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
}
