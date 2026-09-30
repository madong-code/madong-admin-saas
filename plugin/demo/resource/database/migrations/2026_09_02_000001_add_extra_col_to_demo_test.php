<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

/**
 * demo 插件 1.1.0 增量迁移：为测试表新增 extra_col 列
 *
 * 目的：模拟"新版本带来表结构变更"，用于验证升级流程在两种隔离模式下
 * 是否都能把结构变更落到正确的库：
 *   - database(库隔离): 在租户独立库 tenant_{id} 上执行
 *   - field(字段隔离)  : 共享主库, 由平台层 madong-plugin-migrate up 统一建/改, 租户侧跳过
 */
return new class
{
    public function up(Builder $schema): void
    {
        $tablePrefix = config('admin.database.table_prefix');
        $tableName = $tablePrefix . 'demo_demo_test';

        if ($schema->hasTable($tableName) && !$schema->hasColumn($tableName, 'extra_col')) {
            $schema->table($tableName, function (Blueprint $table) {
                $table->string('extra_col', 255)
                    ->nullable()
                    ->default(null)
                    ->comment('1.1.0 新增：扩展字段（升级验证用）')
                    ->after('description');
            });
        }
    }

    public function down(Builder $schema): void
    {
        $tablePrefix = config('admin.database.table_prefix');
        $tableName = $tablePrefix . 'demo_demo_test';

        if ($schema->hasTable($tableName) && $schema->hasColumn($tableName, 'extra_col')) {
            $schema->table($tableName, function (Blueprint $table) {
                $table->dropColumn('extra_col');
            });
        }
    }
};
