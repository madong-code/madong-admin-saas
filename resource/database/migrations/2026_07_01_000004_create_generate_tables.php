<?php

/**
 * 创建代码生成相关表（generate_* 前缀）
 *
 * 表:
 * - generate_table (代码生成表配置表)
 * - generate_column (代码生成列配置表)
 */

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

return new class {

    public function up(Builder $schema): void
    {
        // 代码生成表配置表
        if (!$schema->hasTable('generate_table')) {
            $schema->create('generate_table', function (Blueprint $table) {
                $table->bigInteger('id')->primary()->comment('主键ID');
                $table->unsignedBigInteger('tenant_id')->nullable()->comment('租户ID(雪花ID)');
                $table->index('tenant_id', 'idx_tenant_id');
                $table->string('table_name', 100)->unique()->comment('表名');
                $table->string('table_content', 200)->nullable()->comment('表内容/注释');
                $table->string('module_name', 100)->comment('模块名');
                $table->string('class_name', 100)->nullable()->comment('类名');
                $table->tinyInteger('edit_type')->default(1)->comment('编辑类型');
                $table->string('plugin_name', 100)->nullable()->comment('插件名');
                $table->tinyInteger('order_type')->default(0)->comment('排序类型');
                $table->string('parent_menu', 100)->nullable()->comment('父级菜单');
                $table->text('relations')->nullable()->comment('关联关系');
                $table->integer('push_sync_count')->default(0)->comment('推送同步计数');
                $table->tinyInteger('enabled')->default(1)->comment('状态: 1=启用 0=停用');
                $table->bigInteger('created_at')->nullable()->comment('创建时间');
                $table->bigInteger('created_by')->nullable()->comment('创建者');
                $table->bigInteger('updated_at')->nullable()->comment('修改时间');
                $table->bigInteger('updated_by')->nullable()->comment('更新者');
                $table->bigInteger('deleted_at')->nullable()->comment('删除时间');
                $table->longText('remark')->nullable()->comment('备注');
                $table->index('table_name');
                $table->index('module_name');
            });
        }

        // 代码生成列配置表
        if (!$schema->hasTable('generate_column')) {
            $schema->create('generate_column', function (Blueprint $table) {
                $table->bigInteger('id')->primary()->comment('主键ID');
                $table->unsignedBigInteger('tenant_id')->nullable()->comment('租户ID(雪花ID)');
                $table->index('tenant_id', 'idx_tenant_id');
                $table->bigInteger('table_id')->comment('表ID');
                $table->string('column_name', 100)->comment('列名');
                $table->string('column_comment', 200)->nullable()->comment('列注释');
                $table->string('column_type', 50)->comment('列类型');
                $table->tinyInteger('is_required')->default(0)->comment('是否必填: 1=是 0=否');
                $table->tinyInteger('is_pk')->default(0)->comment('是否主键: 1=是 0=否');
                $table->tinyInteger('is_insert')->default(1)->comment('是否插入字段: 1=是 0=否');
                $table->tinyInteger('is_update')->default(1)->comment('是否更新字段: 1=是 0=否');
                $table->tinyInteger('is_lists')->default(1)->comment('是否列表字段: 1=是 0=否');
                $table->tinyInteger('is_query')->default(0)->comment('是否查询字段: 1=是 0=否');
                $table->tinyInteger('is_search')->default(0)->comment('是否搜索字段: 1=是 0=否');
                $table->string('query_type', 50)->nullable()->comment('查询方式');
                $table->string('view_type', 50)->nullable()->comment('视图类型');
                $table->string('dict_type', 100)->nullable()->comment('字典类型');
                $table->string('plugin', 100)->nullable()->comment('插件');
                $table->string('model', 100)->nullable()->comment('模型');
                $table->integer('sort')->default(0)->comment('排序');
                $table->string('label_key', 100)->nullable()->comment('标签键');
                $table->string('value_key', 100)->nullable()->comment('值键');
                $table->bigInteger('create_time')->nullable()->comment('创建时间');
                $table->bigInteger('update_time')->nullable()->comment('更新时间');
                $table->tinyInteger('is_delete')->default(0)->comment('是否删除: 1=是 0=否');
                $table->tinyInteger('is_order')->default(0)->comment('是否排序: 1=是 0=否');
                $table->string('validate_type', 50)->nullable()->comment('验证类型');
                $table->tinyInteger('enabled')->default(1)->comment('状态: 1=启用 0=停用');
                $table->bigInteger('created_at')->nullable()->comment('创建时间');
                $table->bigInteger('created_by')->nullable()->comment('创建者');
                $table->bigInteger('updated_at')->nullable()->comment('修改时间');
                $table->bigInteger('updated_by')->nullable()->comment('更新者');
                $table->bigInteger('deleted_at')->nullable()->comment('删除时间');
                $table->longText('remark')->nullable()->comment('备注');
                $table->index('table_id');
                $table->index('column_name');
                $table->index('is_query');
                $table->index(['table_id', 'column_name']);
                $table->foreign('table_id')->references('id')->on('generate_table')->onDelete('cascade');
            });
        }

        echo "Created generate tables.\n";
    }

    public function down(Builder $schema): void
    {
        if ($schema->hasTable('generate_column')) {
            $schema->table('generate_column', function (Blueprint $table) {
                $table->dropForeign(['table_id']);
            });
        }
        $schema->dropIfExists('generate_column');
        $schema->dropIfExists('generate_table');
    }
};
