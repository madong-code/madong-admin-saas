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

namespace core\communication\mcp\support;

use app\model\tenant\Tenant;
use core\business\tenant\context\TenantContext;
use core\business\tenant\TenantConnectionManager;
use Illuminate\Database\Connection;
use Illuminate\Database\Query\Builder;
use support\Db;

/**
 * MCP 租户支持（多租户 / 单体 双模式统一入口）
 *
 * 背景：MCP 内置工具以裸表名查询、不依赖 app 模型，此前直接使用 Db 门面，
 * 在多租户下绕过了 BaseModel + TenantScope 的隔离：
 *   - 字段隔离：未按 tenant_id 过滤，可读到其它租户数据；
 *   - 库隔离：走默认连接（平台库），根本读不到租户库。
 * 单体模式（APP_TENANT_ENABLED=false）下租户上下文不激活，本类退化为默认连接 + 不加条件的原行为。
 *
 * 使用约定：
 *   - HTTP 端点：鉴权后调用 activate() 注入租户上下文（请求结束由框架清理）；
 *   - CLI 命令：调用 activate() 后必须在 finally 中 deactivate()；
 *   - 工具取库一律走 connection() / table()，不要再直接用 Db 门面。
 */
final class McpTenant
{
    /**
     * 表是否存在租户字段的进程级缓存（键：连接名.表名.字段名）
     *
     * @var array<string, bool>
     */
    private static array $tenantColumnCache = [];

    /**
     * 注入租户上下文
     *
     * 分支判定与 app\middleware\admin\TenantMiddleware 保持一致：
     * 库隔离建立租户连接，字段隔离仅设置隔离模式。单体模式或租户为空时不做任何事。
     *
     * @param int|string|null $tenantId
     */
    public static function activate(int|string|null $tenantId): void
    {
        if ($tenantId === null || $tenantId === '' || !TenantContext::isTenantEnabled()) {
            return;
        }

        TenantContext::setTenant($tenantId);

        $tenantInfo = TenantContext::getTenantInfo();
        if ($tenantInfo && ($tenantInfo['database_mode'] ?? '') === Tenant::MODE_DATABASE) {
            TenantConnectionManager::setCurrentConnection($tenantId, false);
        } else {
            TenantContext::setIsolationMode('field');
        }
    }

    /**
     * 释放租户上下文（CLI 等长驻/无请求生命周期的入口使用）
     */
    public static function deactivate(): void
    {
        TenantConnectionManager::releaseCurrentConnection();
        TenantContext::clear();
    }

    /**
     * 当前应使用的数据库连接：库隔离下为租户连接，其余场景为默认连接
     */
    public static function connection(): Connection
    {
        if (self::databaseIsolationActive()) {
            $connection = TenantConnectionManager::getCurrentConnection();
            if ($connection !== null) {
                return $connection;
            }
        }

        return Db::connection();
    }

    /**
     * 查询构造器入口：字段隔离下自动补当前租户过滤（表含 tenant_id 时）
     */
    public static function table(string $table): Builder
    {
        $query = self::connection()->table($table);
        self::applyTenantFilter($query, $table);

        return $query;
    }

    /**
     * 是否处于「字段隔离 + 已绑定租户」状态（此时必须按 tenant_id 过滤）
     */
    public static function fieldIsolationActive(): bool
    {
        return TenantContext::isTenantEnabled()
            && TenantContext::isInitialized()
            && !TenantContext::isSuperAdmin()
            && TenantContext::getIsolationMode() === 'field';
    }

    /**
     * 表是否受租户隔离约束（字段隔离生效且表结构含租户字段）
     */
    public static function isTenantScopedTable(string $table): bool
    {
        return self::fieldIsolationActive()
            && self::hasTenantColumn(self::connection(), $table, TenantContext::getTenantColumn());
    }

    private static function databaseIsolationActive(): bool
    {
        return TenantContext::isTenantEnabled()
            && TenantContext::isInitialized()
            && TenantContext::getIsolationMode() === 'database';
    }

    /**
     * 字段隔离过滤：语义对齐 core\business\tenant\scope\TenantScope
     */
    private static function applyTenantFilter(Builder $query, string $table): void
    {
        if (!self::fieldIsolationActive()) {
            return;
        }

        $tenantId = TenantContext::getTenantId();
        if ($tenantId === null || $tenantId === '') {
            return;
        }

        $column = TenantContext::getTenantColumn();
        if (!self::hasTenantColumn($query->getConnection(), $table, $column)) {
            return;
        }

        // 带表名前缀，避免 JOIN 多表同名列歧义
        $query->where($table . '.' . $column, $tenantId);
    }

    /**
     * 表结构是否含租户字段（进程级缓存，键：连接名.表名.字段名）
     */
    private static function hasTenantColumn(Connection $connection, string $table, string $column): bool
    {
        $key = $connection->getName() . '.' . $table . '.' . $column;
        if (!array_key_exists($key, self::$tenantColumnCache)) {
            try {
                // Schema 门面在 webman 容器未绑定，统一用连接自带的 Schema Builder
                self::$tenantColumnCache[$key] = $connection->getSchemaBuilder()->hasColumn($table, $column);
            } catch (\Throwable) {
                self::$tenantColumnCache[$key] = false;
            }
        }

        return self::$tenantColumnCache[$key];
    }
}