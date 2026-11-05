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
use core\business\message\MessageDataSync;
use core\business\tenant\SyncConnection;
use core\business\tenant\context\TenantContext;
use core\foundation\base\BaseService;
use support\Log;

/**
 * 租户插件消息数据同步服务
 *
 * 与 TenantMenuSyncService 保持同一套范式：
 *   - 隔离模式按【租户维度】解析（读 Tenant.database_mode），不依赖全局 TenantContext，
 *     批量/队列场景下 field / database 混杂也能各自落到正确的库。
 *   - 连接由 SyncConnection::getConnectionName(tenantId, mode) 解析：
 *       field    → 主库连接，数据以 tenant_id 隔离
 *       database → 租户独立库连接(tenant_{id})
 *   - 数据以 source='plugin:{key}' 标识，卸载时按 source + tenant_id 精确清理。
 *
 * 平台层安装只负责"上架"，不导入消息数据；租户安装/卸载时由本服务落库/清理。
 *
 * @author Mr.April
 * @since  1.0
 */
class TenantMessageSyncService extends BaseService
{
    /** 插件消息来源前缀 */
    public const SOURCE_PREFIX = 'plugin:';

    /**
     * 按租户解析隔离模式（租户维度，非全局）
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
     */
    public function resolveConnection(string|int $tenantId, string $mode): string
    {
        if ($mode === 'database') {
            return SyncConnection::getConnectionName($tenantId, 'database');
        }

        return SyncConnection::getConnectionName($tenantId, 'field');
    }

    /**
     * 同步插件消息数据到指定租户
     *
     * @param string      $pluginKey 插件编码
     * @param string|int  $tenantId  目标租户
     * @param string|null $mode      隔离模式，null=自动解析
     *
     * @return array{created:int,updated:int,skipped:int,deleted:int}
     */
    public function sync(string $pluginKey, string|int $tenantId, ?string $mode = null): array
    {
        $categories = $this->loadMessageFile($pluginKey);
        if ($categories === null) {
            return ['created' => 0, 'updated' => 0, 'skipped' => 0, 'deleted' => 0];
        }

        $mode ??= $this->resolveMode($tenantId);

        $stat = (new MessageDataSync(
            self::SOURCE_PREFIX . $pluginKey,
            $this->resolveTenantId($tenantId, $mode),
            $this->resolveConnection($tenantId, $mode)
        ))->sync($categories, MessageDataSync::MODE_SYNC);

        $this->log('sync message data ok', compact('pluginKey', 'tenantId', 'mode', 'stat'));

        return $stat;
    }

    /**
     * 清理指定租户的插件消息数据
     *
     * @param string      $pluginKey 插件编码
     * @param string|int  $tenantId  目标租户
     * @param string|null $mode      隔离模式，null=自动解析
     */
    public function purge(string $pluginKey, string|int $tenantId, ?string $mode = null): int
    {
        $mode ??= $this->resolveMode($tenantId);

        $deleted = (new MessageDataSync(
            self::SOURCE_PREFIX . $pluginKey,
            $this->resolveTenantId($tenantId, $mode),
            $this->resolveConnection($tenantId, $mode)
        ))->purge();

        $this->log('purge message data ok', compact('pluginKey', 'tenantId', 'mode', 'deleted'));

        return $deleted;
    }

    /**
     * 单租户模式统一落 tenant_id=0，避免写入租户ID
     */
    protected function resolveTenantId(string|int $tenantId, string $mode): string
    {
        return $mode === 'single' ? '0' : (string)$tenantId;
    }

    /**
     * 读取插件消息数据文件
     */
    protected function loadMessageFile(string $pluginKey): ?array
    {
        $file = base_path('plugin' . DIRECTORY_SEPARATOR . $pluginKey
            . DIRECTORY_SEPARATOR . 'resource' . DIRECTORY_SEPARATOR . 'data'
            . DIRECTORY_SEPARATOR . 'message' . DIRECTORY_SEPARATOR . 'category.php');

        if (!is_file($file)) {
            return null;
        }

        $categories = require $file;

        return is_array($categories) ? $categories : null;
    }

    /**
     * 记录同步日志（失败不影响主流程）
     */
    protected function log(string $message, array $context = []): void
    {
        try {
            Log::channel('plugin')->info("[TenantMessageSync] {$message}", $context);
        } catch (\Throwable $e) {
            // 日志失败忽略
        }
    }
}
