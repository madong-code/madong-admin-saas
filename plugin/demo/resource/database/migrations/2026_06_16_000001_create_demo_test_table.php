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

        if (!$schema->hasTable($tableName)) {
            $schema->create($tableName, function (Blueprint $table) use ($tableName) {
                $table->comment('demo插件-测试表');

                // 主键 - 使用 bigInteger 支持雪花ID
                $table->bigInteger('id')->unsigned()->primary()->comment('主键');

                // 基础字段
                $table->string('name', 100)->comment('名称');
                $table->string('description', 500)->nullable()->comment('描述');

                // 分类
                $table->unsignedBigInteger('category_id')->default(0)->comment('分类ID');
                $table->index('category_id', 'demo_demo_test_category_id_index');

                // 状态
                $table->tinyInteger('status')->default(1)->comment('状态: 1启用 2禁用');
                $table->index('status', 'demo_demo_test_status_index');

                // 排序
                $table->unsignedInteger('sort')->default(0)->comment('排序');

                // 软删除
                $table->unsignedInteger('deleted_at')->nullable()->comment('删除时间');

                // 时间戳
                $table->unsignedInteger('created_at')->comment('创建时间');
                $table->unsignedInteger('updated_at')->comment('更新时间');
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

        if ($schema->hasTable($tableName)) {
            $schema->dropIfExists($tableName);
        }
    }
};
