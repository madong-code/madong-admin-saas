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
 * 租户插件配置服务
 *
 * 背景(问题1):
 *   租户插件的租户级配置原先直接写在平台主表 md_saas_tenant_plugin.config,
 *   这违反租户数据隔离原则: 库隔离(database)租户的业务数据应当留在租户自己的库里,
 *   平台主表只保留"平台级授权账本"(授权状态/占用清单等).
 *
 * 改造(对齐租户数据同步模式 TenantSyncService::syncConfigData):
 *   - 配置按【租户隔离模式】落到对应库的配置表 sys_config:
 *       field    → 主库 sys_config, 以 tenant_id 区分
 *       database → 租户独立库 sys_config, 库中天然隔离
 *   - 以 source='plugin:{key}' 标识插件配置, 与模板同步(source='template')、
 *     租户手动配置(source='manual')互不干扰, 同步/卸载时不会误删租户自定义配置.
 *
 * @author Mr.April
 * @since  1.0
 */
class TenantPluginConfigService extends BaseService
{
    /** 配置归属分组 */
    protected const GROUP_CODE = 'plugin';

    /** 插件配置来源前缀 */
    protected const SOURCE_PREFIX = 'plugin:';

    /**
     * 读取租户插件配置
     *
     * @return array 配置数组(无配置时返回空数组)
     */
    public function get(string|int $tenantId, string $pluginKey): array
    {
        [$mode, $conn] = $this->resolve($tenantId);

        if (!$this->tableExists($conn, 'sys_config')) {
            return [];
        }

        $row = $this->query($conn, $tenantId, $pluginKey, $mode)->first();
        if (!$row) {
            return [];
        }

        $content = (string)($row->content ?? '');
        if ($content === '') {
            return [];
        }

        $decoded = json_decode($content, true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * 写入租户插件配置(整存整取, 不存在则创建)
     */
    public function set(string|int $tenantId, string $pluginKey, array $config): void
    {
        [$mode, $conn] = $this->resolve($tenantId);

        if (!$this->tableExists($conn, 'sys_config')) {
            return;
        }

        $this->ensureConfigSchema($conn);

        $now = time();
        $payload = [
            'group_code' => self::GROUP_CODE,
            'code'       => $pluginKey,
            'name'       => $pluginKey,
            'content'    => json_encode($config, JSON_UNESCAPED_UNICODE),
            'type'       => 'text',
            'enabled'    => 1,
            'source'     => self::SOURCE_PREFIX . $pluginKey,
            'updated_at' => $now,
        ];

        $existing = $this->query($conn, $tenantId, $pluginKey, $mode)->first();

        if ($existing) {
            Db::connection($conn)->table('sys_config')
                ->where('id', $existing->id)
                ->update($payload);
            return;
        }

        Db::connection($conn)->table('sys_config')->insert($payload + [
            'id'         => (int)Snowflake::generate(),
            'tenant_id'  => $tenantId,
            'created_at' => $now,
        ]);
    }

    /**
     * 删除租户插件配置(卸载插件时)
     *
     * 仅删除 source='plugin:{key}' 的配置, 不影响租户手动配置.
     */
    public function delete(string|int $tenantId, string $pluginKey): int
    {
        [$mode, $conn] = $this->resolve($tenantId);

        if (!$this->tableExists($conn, 'sys_config')) {
            return 0;
        }

        return $this->query($conn, $tenantId, $pluginKey, $mode)->delete();
    }

    // ============================================================
    // 内部
    // ============================================================

    /**
     * 解析租户隔离模式与目标连接
     *
     * @return array{0: string, 1: string} [mode, connection]
     */
    protected function resolve(string|int $tenantId): array
    {
        if (TenantContext::isSingleMode()) {
            return ['single', SyncConnection::getConnectionName($tenantId, 'field')];
        }

        $mode = Tenant::withoutGlobalScopes()
            ->where('id', (string)$tenantId)
            ->value('database_mode');

        $mode = in_array($mode, ['field', 'database'], true) ? $mode : 'field';

        return [$mode, SyncConnection::getConnectionName($tenantId, $mode)];
    }

    /**
     * 按隔离模式构建查询: field 需 tenant_id 过滤, database 整库即该租户
     */
    protected function query(string $conn, string|int $tenantId, string $pluginKey, string $mode)
    {
        $query = Db::connection($conn)->table('sys_config')
            ->where('source', self::SOURCE_PREFIX . $pluginKey);

        if ($mode !== 'database') {
            $query->where('tenant_id', $tenantId);
        }

        return $query;
    }

    /**
     * 确保目标库 sys_config 具备 source 列(旧库可能缺失)
     */
    protected function ensureConfigSchema(string $conn): void
    {
        if ($this->columnExists($conn, 'sys_config', 'source')) {
            return;
        }

        Db::connection($conn)->getSchemaBuilder()->table('sys_config', function ($table) {
            $table->string('source', 64)->nullable()->comment('配置来源: plugin:{key} / template / manual');
        });
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
