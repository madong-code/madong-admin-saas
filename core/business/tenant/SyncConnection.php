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
use Illuminate\Container\Container;
use support\Log;

/**
 * 同步连接管理
 *
 * 用于跨租户数据同步时动态注册和获取租户数据库连接。
 * 从 Tenant.database_name 或 Tenant.dbSetting（数据中心自定义数据源）解析真实的数据库名和连接参数。
 */
class SyncConnection
{
    /**
     * 获取适用于当前租户的连接名
     *
     * @param int    $tenantId
     * @param string $mode  field | database
     *
     * @return string 连接名（默认主库 / tenant_{id}）
     */
    public static function getConnectionName(int|string $tenantId, string $mode): string
    {
        if ($mode === 'database') {
            self::register($tenantId);
            return 'tenant_' . $tenantId;
        }
        return TenantConnectionManager::getDefaultConnectionName();
    }

    /**
     * 注册租户数据库连接到 Capsule Manager
     *
     * 从 Tenant 记录中解析真实的数据库配置：
     * 1. 优先使用 dbSetting（通过 db_setting_id 关联的自定义数据源 → 数据中心配置）
     * 2. 其次使用 tenant.database_name（自动记录的自定义名称）
     * 3. 降级到 database_pattern 模式生成
     *
     * @param int $tenantId
     *
     * @return void
     */
    public static function register(int|string $tenantId): void
    {
        $connectionName = 'tenant_' . $tenantId;

        // 已注册则跳过
        $connections = config('database.connections', []);
        if (isset($connections[$connectionName])) {
            return;
        }

        // 查询租户记录及其数据源配置
        $tenant = Tenant::withoutGlobalScopes()->with('dbSetting')->find($tenantId);
        if (!$tenant || $tenant->database_mode !== 'database') {
            throw new \RuntimeException("租户 {$tenantId} 未配置数据库隔离模式或不存在");
        }

        // 构建连接配置
        if ($tenant->dbSetting) {
            // 数据中心自定义数据源 — 完整的 host/port/username/password/database
            $config = $tenant->dbSetting->toConfig();
            // toConfig() 不含 pool 参数，补上以启用连接池管理（心跳检测、空闲回收等）
            $config['pool'] = TenantConnectionManager::getTenantPoolConfig();
        } else {
            // 模板模式：用默认连接模板 + 实际数据库名
            $defaultConn = TenantConnectionManager::getDefaultConnectionName();
            $template = $connections[$defaultConn] ?? throw new \RuntimeException("缺少默认连接配置 [{$defaultConn}]");

            if (!empty($tenant->database_name)) {
                $database = $tenant->database_name;
            } else {
                // 降级到模式生成
                $pattern  = config('tenant.database_pattern', 'saas_tenant_{tenant_id}');
                $database = str_replace('{tenant_id}', (string)$tenantId, $pattern);
            }

            $config = array_merge($template, [
                'database' => $database,
            ]);
        }

        // 注册到 webman config
        config("database.connections.{$connectionName}", $config);

        // 注册到 Illuminate 容器 config（Capsule 的 DatabaseManager 从这读取）
        $illuminateConfig = Container::getInstance()['config'];
        $conns = $illuminateConfig['database.connections'] ?? [];
        $conns[$connectionName] = $config;
        $illuminateConfig['database.connections'] = $conns;

        Log::info('[SyncConnection] 已注册租户数据库连接', [
            'tenant_id' => $tenantId,
            'connection' => $connectionName,
            'database'  => $config['database'],
            'host'      => $config['host'] ?? 'default',
            'source'    => $tenant->dbSetting ? 'db_setting(数据中心)' : 'tenant.database_name',
        ]);
    }
}
