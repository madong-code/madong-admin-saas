<?php
/**
 * 插件生命周期相关配置 (WP4)
 *
 * 集中管理: 同步阈值、批大小、队列名、Redis key、重试策略。
 * 与 plugin.demo.config.info.php 区分: 本文件是后端核心配置, 不是插件 manifest。
 */

return [
    // 同步分批阈值: 受影响租户数 ≤ sync_threshold_inline 时走 SSE 实时流(Generator 直推);
    //                > sync_threshold_inline 时入 Redis 队列 + 审计表(saas_tenant_plugin_sync_jobs)。
    'sync' => [
        'threshold_inline'  => (int)env('PLUGIN_SYNC_INLINE_THRESHOLD', 50),
        'batch_size'        => (int)env('PLUGIN_SYNC_BATCH_SIZE', 10),       // 每批处理的租户数
        'tenant_timeout_ms' => (int)env('PLUGIN_SYNC_TENANT_TIMEOUT', 30000), // 单租户超时
    ],

    // Redis 队列(webman/redis-queue)
    'queue' => [
        'name'         => env('PLUGIN_SYNC_QUEUE_NAME', 'plugin-sync-jobs'),
        'retry_times'  => (int)env('PLUGIN_SYNC_RETRY_TIMES', 3),
        'retry_delay'  => (int)env('PLUGIN_SYNC_RETRY_DELAY', 10),  // 秒
    ],

    // 占用租户阻断: 平台级 force=true 卸载时, 若有租户仍在使用, 默认阻断(可强制)
    'uninstall' => [
        'block_when_occupied' => true,   // 强制卸载时是否默认阻断
        'cascade'             => true,   // 是否先逐租户清理
    ],

    // 菜单同步 (WP3)
    'menu' => [
        'soft_deprecate'  => true,       // 废弃菜单是否软删(enabled=0 + 记录 deprecated_version)
        'fingerprint_fields' => [        // 变化检测字段
            'title', 'path', 'component', 'icon', 'sort', 'type', 'is_show', 'is_sync',
        ],
    ],
];
