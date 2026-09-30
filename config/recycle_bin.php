<?php
/**
 * 数据回收站配置
 *
 * 支持三种多租户模式的数据回收策略：
 *   - single:    非租户模式（单租户），数据存入主库回收表
 *   - field:     字段隔离模式，数据存入主库回收表 + tenant_id
 *   - database:  库隔离模式，数据存入对应租户库的回收表
 *
 * 文档位置: docs/saas/03-配置说明.md
 */

return [

    // ============================================
    // 全局默认配置
    // ============================================

    'default' => [
        'enabled'       => true,        // 是否启用回收站
        'strategy'      => 'physical',  // physical | logical (physical=物理删除前进回收站)
        'storage_days'  => 30,          // 回收站数据保留天数（0=永久）
        'auto_cleanup'  => true,        // 是否自动清理过期数据
    ],


    // ============================================
    // 多租户模式配置
    // ============================================

    'tenant' => [
        // 非租户模式（单租户）
        'single' => [
            'connection' => 'mysql',           // 回收表所在连接
            'table'      => 'sys_recycle_bin', // 回收表名称
            'tenant_id'  => 0,                 // 默认租户ID
        ],
        // 字段隔离模式
        'field' => [
            'connection' => 'mysql',           // 回收表在主库
            'table'      => 'sys_recycle_bin',
            'tenant_id'  => true,               // 记录 tenant_id
        ],
        // 库隔离模式
        'database' => [
            'connection' => 'dynamic',          // 动态连接（根据租户ID）
            'table'      => 'sys_recycle_bin',
            'tenant_id'  => true,               // 记录 tenant_id
        ],
    ],


    // ============================================
    // 表级配置（支持关联表恢复）
    // ============================================

    'tables' => [
        'sys_admin' => [
            'enabled'   => true,
            'relations' => [
                [
                    'name'          => 'roles',       // 模型关联方法名
                    'type'          => 'belongsToMany',
                    'related_table' => 'sys_admin_role',
                    'foreign_key'   => 'admin_id',
                    'local_key'     => 'id',
                ],
            ],
        ],
        'sys_menu' => [
            'enabled'   => true,
            'relations' => [
                [
                    'name'          => 'roles',
                    'type'          => 'belongsToMany',
                    'related_table' => 'sys_role_menu',
                    'foreign_key'   => 'menu_id',
                    'local_key'     => 'id',
                ],
            ],
        ],
    ],


    // ============================================
    // 排除字段（所有表通用）
    // ============================================

    'exclude_fields' => [
        'deleted_at',  // 软删除时间戳
        'updated_at',  // 更新时间（恢复时自动生成）
    ],
];
