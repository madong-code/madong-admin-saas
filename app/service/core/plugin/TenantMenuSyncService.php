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

namespace app\service\core\plugin;

use app\model\tenant\Tenant;
use core\business\tenant\context\TenantContext;
use core\business\tenant\SyncConnection;
use core\foundation\base\BaseService;
use core\io\uuid\Snowflake;
use support\Db;

/**
 * 租户插件菜单同步服务
 *
 * 与"租户数据同步模式"(TenantSyncService)保持同一套范式:
 *   - 隔离模式按【租户维度】解析(读 Tenant.database_mode), 不依赖全局 TenantContext,
 *     因此批量/队列场景(不同租户 field/database 混杂)也能各自落到正确的库.
 *   - 连接由 SyncConnection::getConnectionName(tenantId, mode) 解析:
 *       field    → 主库连接, 操作 sys_menu 时必须带 tenant_id 过滤
 *       database → 租户独立库连接(tenant_{id}), 库中天然隔离, 不带 tenant_id 过滤
 *   - 统一操作 sys_menu(不再混用 sys_tenant_menu), 插件菜单以 source='plugin:{key}' 标识
 *   - 三分类: added(新增) / updated(内容变更) / deprecated(清单移除, 软删 enabled=0)
 *     避免卸载时全删重装导致租户自定义排序与角色授权关系被破坏
 *
 * 配置存储同样遵循该范式(见 TenantPluginConfigService), 不再写在平台主表.
 *
 * @author Mr.April
 * @since  1.0
 */
class TenantMenuSyncService extends BaseService
{
    /** 内容指纹涉及的可变字段(任一变化视为"updated") */
    protected const FINGERPRINT_FIELDS = [
        'title', 'path', 'component', 'icon', 'sort', 'type', 'is_show', 'is_sync',
    ];

    /** 插件菜单来源前缀 */
    public const SOURCE_PREFIX = 'plugin:';

    // ============================================================
    // 对外: 上下文解析
    // ============================================================

    /**
     * 按租户解析隔离模式(租户维度, 非全局)
     *
     * @return string single|field|database
     */
    public function resolveMode(string|int $tenantId): string
    {
        if (TenantContext::isSingleMode()) {
            return 'single';
        }

        $mode = Tenant::withoutGlobalScopes()
            ->where('id', (string)$tenantId)
            ->value('database_mode');

        return in_array($mode, ['field', 'database'], true) ? $mode : 'field';
    }

    /**
     * 按租户+模式解析连接名
     *
     * database 模式会按需注册 tenant_{id} 连接(读 Tenant.dbSetting / database_name).
     */
    public function resolveConnection(string|int $tenantId, string $mode): string
    {
        if ($mode === 'database') {
            return SyncConnection::getConnectionName($tenantId, 'database');
        }
        return SyncConnection::getConnectionName($tenantId, 'field');
    }

    // ============================================================
    // 对外: 三分类 diff / apply / preview
    // ============================================================

    /**
     * 三分类 diff
     *
     * @return array{added: array, updated: array, deprecated: array, summary: array}
     */
    public function diff(string|int $tenantId, string $pluginKey, array $manifestItems): array
    {
        $mode = $this->resolveMode($tenantId);
        $conn = $this->resolveConnection($tenantId, $mode);
        $this->ensureMenuSchema($conn);

        $manifestByCode = [];
        foreach ($manifestItems as $item) {
            $code = $item['code'] ?? null;
            if (empty($code)) {
                continue;
            }
            $manifestByCode[$code] = $item;
        }

        $existing = $this->loadExisting($conn, $tenantId, $pluginKey, $mode);
        $existingByCode = [];
        foreach ($existing as $row) {
            $existingByCode[$row['code']] = $row;
        }

        $added = [];
        $updated = [];
        $deprecated = [];

        foreach ($manifestByCode as $code => $item) {
            $fingerprint = $this->fingerprint($item);
            if (!isset($existingByCode[$code])) {
                $added[] = [
                    'code'   => $code,
                    'item'   => $item,
                    'reason' => 'manifest has, tenant missing',
                ];
                continue;
            }
            if ($existingByCode[$code]['fingerprint'] !== $fingerprint) {
                $updated[] = [
                    'code'            => $code,
                    'item'            => $item,
                    'existing'        => $existingByCode[$code],
                    'reason'          => 'content changed',
                    'old_fingerprint' => $existingByCode[$code]['fingerprint'],
                    'new_fingerprint' => $fingerprint,
                ];
            }
        }

        foreach ($existingByCode as $code => $row) {
            if (!isset($manifestByCode[$code]) && (int)($row['enabled'] ?? 0) === 1) {
                $deprecated[] = [
                    'code'     => $code,
                    'existing' => $row,
                    'reason'   => 'tenant has, manifest missing',
                ];
            }
        }

        return [
            'added'      => $added,
            'updated'    => $updated,
            'deprecated' => $deprecated,
            'summary'    => [
                'tenant_id'        => (string)$tenantId,
                'plugin_key'       => $pluginKey,
                'isolation_mode'   => $mode,
                'connection'       => $conn,
                'manifest_total'   => count($manifestByCode),
                'existing_total'   => count($existingByCode),
                'added_count'      => count($added),
                'updated_count'    => count($updated),
                'deprecated_count' => count($deprecated),
            ],
        ];
    }

    /**
     * 应用 diff 结果(写库)
     */
    public function apply(string|int $tenantId, string $pluginKey, array $diff, string $version = '1.0.0'): array
    {
        $mode = $this->resolveMode($tenantId);
        $conn = $this->resolveConnection($tenantId, $mode);

        $stats = [
            'inserted' => 0,
            'updated'  => 0,
            'disabled' => 0,
            'errors'   => [],
        ];

        $now = time();

        foreach ($diff['added'] ?? [] as $entry) {
            try {
                $this->insertOne($conn, $tenantId, $pluginKey, $entry['item'], $mode, $now);
                $stats['inserted']++;
            } catch (\Throwable $e) {
                $stats['errors'][] = ['code' => $entry['code'], 'action' => 'add', 'msg' => $e->getMessage()];
            }
        }

        foreach ($diff['updated'] ?? [] as $entry) {
            try {
                $this->updateOne($conn, $tenantId, $entry['code'], $entry['item'], $mode, $now);
                $stats['updated']++;
            } catch (\Throwable $e) {
                $stats['errors'][] = ['code' => $entry['code'], 'action' => 'update', 'msg' => $e->getMessage()];
            }
        }

        foreach ($diff['deprecated'] ?? [] as $entry) {
            try {
                $this->disableOne($conn, $tenantId, $entry['code'], $version, $mode, $now);
                $stats['disabled']++;
            } catch (\Throwable $e) {
                $stats['errors'][] = ['code' => $entry['code'], 'action' => 'deprecate', 'msg' => $e->getMessage()];
            }
        }

        $stats['isolation_mode'] = $mode;
        $stats['connection']     = $conn;

        return $stats;
    }

    /**
     * 干跑预览(供前端"待升级"展示, 不写库)
     */
    public function preview(string|int $tenantId, string $pluginKey, array $manifestItems): array
    {
        return $this->diff($tenantId, $pluginKey, $manifestItems);
    }

    /**
     * 清理插件菜单(卸载)
     *
     * 按隔离模式删除对应库中 source='plugin:{key}' 的菜单:
     *   field    → 主库, 按 tenant_id 过滤
     *   database → 租户独立库, 不过滤 tenant_id
     *
     * @return int 删除行数
     */
    public function clear(string|int $tenantId, string $pluginKey): int
    {
        $mode = $this->resolveMode($tenantId);
        $conn = $this->resolveConnection($tenantId, $mode);

        if (!$this->tableExists($conn, 'sys_menu')) {
            return 0;
        }

        $query = Db::connection($conn)->table('sys_menu')
            ->where('source', self::SOURCE_PREFIX . $pluginKey);

        if ($mode !== 'database') {
            $query->where('tenant_id', $tenantId);
        }

        return $query->delete();
    }

    // ============================================================
    // 内部
    // ============================================================

    protected function loadExisting(string $conn, string|int $tenantId, string $pluginKey, string $mode): array
    {
        if (!$this->tableExists($conn, 'sys_menu')) {
            return [];
        }

        $query = Db::connection($conn)->table('sys_menu')
            ->where('source', self::SOURCE_PREFIX . $pluginKey);

        // 仅 field/single 模式需要 tenant_id 过滤; database 模式整库即该租户
        if ($mode !== 'database') {
            $query->where('tenant_id', $tenantId);
        }

        $rows = $query->select([
            'id', 'pid', 'code', 'title', 'path', 'component',
            'icon', 'sort', 'type', 'is_show', 'is_sync', 'enabled',
        ])->get()->toArray();

        $result = [];
        foreach ($rows as $row) {
            $arr = (array)$row;
            $arr['fingerprint'] = $this->fingerprintFromRow($arr);
            $result[] = $arr;
        }

        return $result;
    }

    protected function fingerprint(array $item): string
    {
        $values = [];
        foreach (self::FINGERPRINT_FIELDS as $field) {
            $values[$field] = (string)($item[$field] ?? '');
        }
        return md5(json_encode($values, JSON_UNESCAPED_UNICODE));
    }

    protected function fingerprintFromRow(array $row): string
    {
        $values = [];
        foreach (self::FINGERPRINT_FIELDS as $field) {
            $values[$field] = (string)($row[$field] ?? '');
        }
        return md5(json_encode($values, JSON_UNESCAPED_UNICODE));
    }

    protected function insertOne(string $conn, string|int $tenantId, string $pluginKey, array $item, string $mode, int $now): void
    {
        $pid = (int)($item['pid'] ?? 0);
        if ($pid === 0 && !empty($item['pid_code'])) {
            $pid = $this->resolveParentId($conn, $tenantId, (string)$item['pid_code'], $mode);
        }

        Db::connection($conn)->table('sys_menu')->insert([
            'id'         => (int)Snowflake::generate(),
            'pid'        => $pid,
            'tenant_id'  => $tenantId,
            'app'        => $item['app'] ?? 'admin',
            'source'     => self::SOURCE_PREFIX . $pluginKey,
            'title'      => (string)($item['title'] ?? $item['name'] ?? ''),
            'code'       => (string)$item['code'],
            'level'      => (int)($item['level'] ?? 1),
            'type'       => (int)($item['type'] ?? 1),
            'sort'       => (int)($item['sort'] ?? 0),
            'path'       => (string)($item['path'] ?? ''),
            'component'  => (string)($item['component'] ?? ''),
            'icon'       => (string)($item['icon'] ?? ''),
            'is_show'    => (int)($item['is_show'] ?? 1),
            'is_link'    => (int)($item['is_link'] ?? 0),
            'is_cache'   => (int)($item['is_cache'] ?? 0),
            'is_sync'    => (int)($item['is_sync'] ?? 0),
            'enabled'    => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    protected function updateOne(string $conn, string|int $tenantId, string $code, array $item, string $mode, int $now): void
    {
        $query = Db::connection($conn)->table('sys_menu')
            ->where('source', self::SOURCE_PREFIX . ($item['_plugin_key'] ?? ''))
            ->where('code', $code);

        if ($mode !== 'database') {
            $query->where('tenant_id', $tenantId);
        }

        $query->update([
            'title'      => (string)($item['title'] ?? $item['name'] ?? ''),
            'path'       => (string)($item['path'] ?? ''),
            'component'  => (string)($item['component'] ?? ''),
            'icon'       => (string)($item['icon'] ?? ''),
            'sort'       => (int)($item['sort'] ?? 0),
            'type'       => (int)($item['type'] ?? 1),
            'is_show'    => (int)($item['is_show'] ?? 1),
            'is_sync'    => (int)($item['is_sync'] ?? 0),
            'updated_at' => $now,
        ]);
    }

    /**
     * 废弃菜单: 软删除(enabled=0) + 记录废弃版本
     *
     * 统一操作 sys_menu(修复此前误写 sys_tenant_menu 导致废弃不生效的问题).
     */
    protected function disableOne(string $conn, string|int $tenantId, string $code, string $version, string $mode, int $now): void
    {
        $query = Db::connection($conn)->table('sys_menu')
            ->where('code', $code)
            ->where('enabled', 1);

        if ($mode !== 'database') {
            $query->where('tenant_id', $tenantId);
        }

        $update = [
            'enabled'    => 0,
            'updated_at' => $now,
        ];

        // deprecated_version 列可能存在(主库)也可能缺失, 存在时才写留痕
        if ($this->columnExists($conn, 'sys_menu', 'deprecated_version')) {
            $update['deprecated_version'] = $version;
        }

        $query->update($update);
    }

    protected function resolveParentId(string $conn, string|int $tenantId, string $pidCode, string $mode): int
    {
        $query = Db::connection($conn)->table('sys_menu')->where('code', $pidCode);
        if ($mode !== 'database') {
            $query->where('tenant_id', $tenantId);
        }

        $row = $query->select(['id'])->first();
        return $row ? (int)$row->id : 0;
    }

    /**
     * 确保目标库 sys_menu 具备插件同步所需列
     *
     * 旧租户库可能缺 source / enabled / deprecated_version, 缺则自动补齐.
     */
    protected function ensureMenuSchema(string $conn): void
    {
        if (!$this->tableExists($conn, 'sys_menu')) {
            return;
        }

        $schema = Db::connection($conn)->getSchemaBuilder();

        if (!$this->columnExists($conn, 'sys_menu', 'source')) {
            $schema->table('sys_menu', function ($table) {
                $table->string('source', 64)->nullable()->comment('菜单来源: plugin:{key} / template');
            });
        }

        if (!$this->columnExists($conn, 'sys_menu', 'enabled')) {
            $schema->table('sys_menu', function ($table) {
                $table->tinyInteger('enabled')->default(1)->comment('是否启用');
            });
        }
    }

    protected function tableExists(string $conn, string $table): bool
    {
        try {
            return Db::connection($conn)->getSchemaBuilder()->hasTable($table);
        } catch (\Throwable $e) {
            return false;
        }
    }

    protected function columnExists(string $conn, string $table, string $column): bool
    {
        try {
            return Db::connection($conn)->getSchemaBuilder()->hasColumn($table, $column);
        } catch (\Throwable $e) {
            return false;
        }
    }
}
