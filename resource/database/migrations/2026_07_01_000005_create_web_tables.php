<?php

/**
 * 创建 Web 前端相关表（web_* 前缀）
 *
 * 表:
 * - web_menu (Web菜单表, 含 template_id)
 * - web_link (友情链接表)
 * - web_adv (广告表)
 */

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

return new class {

    public function up(Builder $schema): void
    {
        // Web菜单表
        if (!$schema->hasTable('web_menu')) {
            $schema->create('web_menu', function (Blueprint $table) {
                $table->bigInteger('id')->primary()->comment('主键');
                $table->unsignedBigInteger('tenant_id')->nullable()->comment('租户ID(雪花ID)');
                $table->bigInteger('template_id')->nullable()->comment('菜单模板ID(saas_template_web_menu.id)');
                $table->index('tenant_id', 'idx_tenant_id');
                $table->bigInteger('pid')->default(0)->comment('父ID');
                $table->string('app', 32)->default('web')->comment('应用编码');
                $table->string('category', 32)->default('1')->comment('菜单分类');
                $table->string('source', 50)->default('system')->comment('菜单来源');
                $table->string('code', 64)->nullable()->comment('唯一编码');
                $table->tinyInteger('is_public')->default(0)->comment('是否公开菜单: 0否(需权限) 1是(所有人可见)');
                $table->tinyInteger('is_no_auth')->default(0)->comment('is_public=0时是否跳过权限校验: 0=需权限 1=仅需登录');
                $table->string('name', 64)->comment('菜单名称');
                $table->string('url', 255)->nullable()->comment('链接地址');
                $table->string('icon', 64)->nullable()->comment('菜单图标');
                $table->integer('level')->default(1)->comment('菜单级别');
                $table->integer('type')->default(1)->comment('菜单类型: 1目录 2菜单 3按钮 4接口 5内链 6外链');
                $table->bigInteger('sort')->default(999)->comment('排序');
                $table->integer('target')->default(1)->comment('打开方式: 1当前窗口 2新窗口');
                $table->json('extra')->nullable()->comment('扩展数据:JSON格式任意配置');
                $table->tinyInteger('is_show')->default(1)->comment('是否显示: 0否 1是');
                $table->tinyInteger('enabled')->default(1)->comment('状态: 1正常 0停用');
                $table->bigInteger('created_at')->nullable()->comment('创建时间');
                $table->bigInteger('updated_at')->nullable()->comment('修改时间');
                $table->bigInteger('deleted_at')->nullable()->comment('删除时间');
                $table->index('code');
                $table->index('app');
            });
        }

        // 友情链接表
        if (!$schema->hasTable('web_link')) {
            $schema->create('web_link', function (Blueprint $table) {
                $table->bigInteger('id')->primary()->comment('主键');
                $table->unsignedBigInteger('tenant_id')->nullable()->comment('租户ID(雪花ID)');
                $table->index('tenant_id', 'idx_tenant_id');
                $table->string('name', 64)->comment('链接名称');
                $table->string('url', 255)->comment('链接地址');
                $table->string('logo', 255)->nullable()->comment('链接 logo');
                $table->string('description', 255)->nullable()->comment('链接描述');
                $table->string('category', 32)->default('footer')->comment('链接分类');
                $table->string('target', 10)->default('_blank')->comment('打开方式');
                $table->tinyInteger('enabled')->default(1)->comment('状态: 1正常 0停用');
                $table->smallInteger('sort')->default(0)->comment('排序');
                $table->integer('click_count')->default(0)->comment('点击次数');
                $table->bigInteger('created_at')->nullable()->comment('创建时间');
                $table->bigInteger('updated_at')->nullable()->comment('修改时间');
                $table->bigInteger('deleted_at')->nullable()->comment('删除时间');
            });
        }

        // 广告表
        if (!$schema->hasTable('web_adv')) {
            $schema->create('web_adv', function (Blueprint $table) {
                $table->bigInteger('id')->primary()->comment('主键');
                $table->unsignedBigInteger('tenant_id')->nullable()->comment('租户ID(雪花ID)');
                $table->index('tenant_id', 'idx_tenant_id');
                $table->string('title', 64)->comment('广告标题');
                $table->string('link', 255)->nullable()->comment('链接地址');
                $table->string('image', 255)->comment('广告图片');
                $table->string('description', 500)->nullable()->comment('广告描述');
                $table->integer('sort')->default(0)->comment('排序');
                $table->tinyInteger('enabled')->default(1)->comment('状态: 1正常 0停用');
                $table->bigInteger('start_time')->nullable()->comment('开始时间');
                $table->bigInteger('end_time')->nullable()->comment('结束时间');
                $table->bigInteger('created_at')->nullable()->comment('创建时间');
                $table->bigInteger('updated_at')->nullable()->comment('修改时间');
                $table->bigInteger('deleted_at')->nullable()->comment('删除时间');
            });
        }

        echo "Created web tables.\n";
    }

    public function down(Builder $schema): void
    {
        $schema->dropIfExists('web_adv');
        $schema->dropIfExists('web_link');
        $schema->dropIfExists('web_menu');
    }
};
