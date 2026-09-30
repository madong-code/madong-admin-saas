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

use core\business\plugin\PluginInstall;
use core\business\tenant\SyncConnection;
use core\foundation\base\BaseService;
use support\Db;

/**
 * 租户插件迁移服务
 *
 * 按租户隔离模式决定"是否执行迁移 / 在哪个库执行":
 *   - field(字段隔离)   : 共享主库, 业务表由平台安装插件时统一创建, 租户侧【跳过】迁移
 *   - database(库隔离)  : 切到租户独立库连接(tenant_{id})执行迁移, 数据完全留在租户库
 *
 * 执行策略(与 TenantPluginService 原有逻辑一致, 抽出来供 Orchestrator/批量/队列共用):
 *   1. 优先调用插件 Install 类(继承 PluginInstall, 自带迁移/种子执行)
 *   2. 否则兜底执行 plugin/{key}/resource/database/migrations/*.php
 *
 * @author Mr.April
 * @since  1.0
 */
class TenantPluginMigrationService extends BaseService
{
    /**
     * 执行迁移
     *
     * @param string      $pluginKey   插件编码
     * @param string|int  $tenantId    租户ID
     * @param string      $action      install|update|uninstall
     * @param string      $version     目标版本
     * @param string      $mode        field|database
     * @param string|null $oldVersion  旧版本(仅 update 需要)
     *
     * @return array{executed: int, errors: array, skipped: bool, reason: string}
     */
    public function run(
        string $pluginKey,
        string|int $tenantId,
        string $action,
        string $version,
        string $mode,
        ?string $oldVersion = null
    ): array {
        // 字段隔离: 共享主库, 业务表由平台安装时创建, 租户侧不重复建表
        if ($mode !== 'database') {
            return [
                'executed' => 0,
                'errors'   => [],
                'skipped'  => true,
                'reason'   => '字段隔离模式共享主库, 跳过租户侧迁移',
            ];
        }

        $conn = SyncConnection::getConnectionName($tenantId, 'database');

        // 1. 优先使用插件 Install 类
        $installClass = "\\plugin\\{$pluginKey}\\Install";
        if (class_exists($installClass)) {
            $installer = new $installClass();
            if ($installer instanceof PluginInstall) {
                $installer->setContext('tenant');
                $installer->setTenantId((string)$tenantId);
                $installer->setConnection($conn);

                match ($action) {
                    'install'   => $installer->install($version),
                    'uninstall' => $installer->uninstall($version),
                    'update'    => $installer->update($oldVersion ?? '1.0.0', $version),
                    default     => null,
                };

                return [
                    'executed' => 1,
                    'errors'   => [],
                    'skipped'  => false,
                    'reason'   => '',
                ];
            }
        }

        // 2. 兜底: 执行插件 migrations 文件
        $migrationDir = base_path('plugin' . DIRECTORY_SEPARATOR . $pluginKey
            . DIRECTORY_SEPARATOR . 'resource' . DIRECTORY_SEPARATOR . 'database'
            . DIRECTORY_SEPARATOR . 'migrations');

        if (!is_dir($migrationDir)) {
            return [
                'executed' => 0,
                'errors'   => [],
                'skipped'  => true,
                'reason'   => '插件无迁移文件',
            ];
        }

        $schema   = Db::connection($conn)->getSchemaBuilder();
        $executed = 0;
        $errors   = [];

        foreach (glob($migrationDir . DIRECTORY_SEPARATOR . '*.php') as $file) {
            try {
                $migration = require $file;
                if ($action === 'uninstall' && is_object($migration) && method_exists($migration, 'down')) {
                    $migration->down($schema);
                } elseif (is_object($migration) && method_exists($migration, 'up')) {
                    $migration->up($schema);
                }
                $executed++;
            } catch (\Throwable $e) {
                $errors[] = basename($file) . ': ' . $e->getMessage();
            }
        }

        return [
            'executed' => $executed,
            'errors'   => $errors,
            'skipped'  => false,
            'reason'   => '',
        ];
    }
}
