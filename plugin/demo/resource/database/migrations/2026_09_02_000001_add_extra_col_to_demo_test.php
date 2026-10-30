<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

return new class
{
    /**
     * Run the migrations.
     */
    public function up(Builder $schema): void
    {
        $tablePrefix = config('admin.database.table_prefix');
        $tableName = $tablePrefix . 'demo_demo_test';

        if ($schema->hasTable($tableName) && !$schema->hasColumn($tableName, 'extra_col')) {
            $schema->table($tableName, function (Blueprint $table) {
                $table->string('extra_col', 255)->nullable()->comment('升级测试新增列');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
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
