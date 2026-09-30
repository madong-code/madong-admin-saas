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

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

/**
 * 平台运营端全部表（SaaS 基础 + 菜单/配置/字典模板）
 *
 * 包含表:
 *   SaaS 基础:
 *   1. saas_subscription - 套餐订阅表
 *   2. saas_tenant - 租户表
 *   3. saas_db_setting - 多数据源配置表
 *   4. saas_permission - 权限定义表
 *   5. saas_subscription_permission - 套餐-权限关联表
 *   6. saas_tenant_subscription - 租户-套餐订阅关联表
 *   7. saas_tenant_log - 租户操作日志表
 *   9. saas_tenant_resource - 租户资源使用表
 *   10. saas_queue_message - 平台队列消息表
 *   11. saas_template_menu - 菜单模板表
 *   12. saas_template_config - 配置模板表
 *   13. saas_template_web_menu - 前端菜单模板表
 *   14. saas_template_dict - 字典模板表
 *   15. saas_template_dict_item - 字典模板项表
 */
return new class
{
    private Builder $schema;

    public function up(Builder $schema): void
    {
        $this->schema = $schema;

        // 1. 套餐订阅表
        if (!$schema->hasTable('saas_subscription')) {
            $schema->create('saas_subscription', function (Blueprint $table) {
                $table->id('id')->comment('雪花ID');
                $table->string('code', 50)->unique('uk_code')->comment('套餐代码');
                $table->string('name', 100)->comment('套餐名称');
                $table->string('description', 500)->nullable()->comment('套餐描述');
                $table->decimal('price', 10, 2)->default(0.00)->comment('价格/月');
                $table->decimal('price_year', 10, 2)->default(0.00)->comment('价格/年');
                $table->integer('max_users')->default(0)->comment('最大用户数 (0=无限制)');
                $table->bigInteger('max_storage')->default(0)->comment('最大存储MB (0=无限制)');
                $table->integer('max_agents')->default(0)->comment('最大坐席数');
                $table->integer('rate_limit')->default(0)->comment('API调用限制/天 (0=无限制)');
                $table->integer('sort')->default(0)->comment('排序');
                $table->boolean('is_trial')->default(false)->comment('是否支持试用');
                $table->integer('trial_days')->default(0)->comment('试用天数');
                $table->string('status', 20)->default('active')->comment('状态: active-正常 disabled-禁用');
                $table->boolean('is_default')->default(false)->comment('是否默认套餐');
                $table->json('settings')->nullable()->comment('其他配置');
                $table->bigInteger('created_at')->nullable()->comment('创建时间');
                $table->bigInteger('updated_at')->nullable()->comment('更新时间');
                $table->bigInteger('deleted_at')->nullable()->comment('删除时间');

                $table->index('status', 'idx_status');
                $table->index('sort', 'idx_sort');
                $table->index('is_default', 'idx_is_default');
            });
        }

        // 2. 租户表
        if (!$schema->hasTable('saas_tenant')) {
            $schema->create('saas_tenant', function (Blueprint $table) {
                $table->id('id')->comment('雪花ID');
                $table->string('name', 100)->comment('租户名称');
                $table->string('code', 50)->unique('uk_code')->comment('租户编码(唯一标识)');
                $table->string('status', 20)->default('active')->comment('状态: active-正常 suspended-暂停 cancelled-注销');
                $table->string('effective_mode', 20)->default('immediate')->comment('生效方式: immediate-永久生效 specified-指定时间');
                $table->bigInteger('start_time')->nullable()->comment('生效时间戳');
                $table->string('database_mode', 20)->default('field')->comment('隔离模式: field-字段 database-库');
                // 已改用 1 个租户支持多个套餐（saas_tenant_subscription），不再保留单一 subscription_id
                $table->unsignedBigInteger('db_setting_id')->nullable()->comment('关联数据源ID');
                $table->string('database_name', 100)->nullable()->comment('独立数据库名(库隔离模式)');
                $table->string('domain', 191)->nullable()->comment('绑定域名');
                $table->string('logo', 255)->nullable()->comment('租户Logo');
                $table->string('contact_name', 50)->nullable()->comment('联系人姓名');
                $table->string('contact_phone', 20)->nullable()->comment('联系电话');
                $table->string('contact_email', 100)->nullable()->comment('联系邮箱');
                $table->string('contact_address', 255)->nullable()->comment('联系地址');
                $table->string('industry', 50)->nullable()->comment('行业');
                $table->string('province', 50)->nullable()->comment('省份');
                $table->string('city', 50)->nullable()->comment('城市');
                $table->string('system_name', 100)->nullable()->comment('系统名称');
                $table->bigInteger('expire_time')->nullable()->comment('到期时间戳');
                $table->integer('sort')->default(0)->comment('排序');
                $table->json('settings')->nullable()->comment('租户配置JSON');
                $table->string('suspend_reason', 255)->nullable()->comment('暂停原因');
                $table->bigInteger('suspend_time')->nullable()->comment('暂停时间戳');
                $table->json('database_config')->nullable()->comment('数据库配置');
                $table->json('white_ips')->nullable()->comment('IP白名单');
                $table->json('allowed_origins')->nullable()->comment('允许的域名');
                $table->bigInteger('created_at')->nullable()->comment('创建时间');
                $table->bigInteger('updated_at')->nullable()->comment('更新时间');
                $table->bigInteger('deleted_at')->nullable()->comment('删除时间');

                $table->index('status', 'idx_status');
                $table->index('db_setting_id', 'idx_db_setting_id');
                $table->index('domain', 'idx_domain');
                $table->index('expire_time', 'idx_expire_time');
                $table->index('sort', 'idx_sort');
                $table->index('created_at', 'idx_created_at');
            });
        }

        // 3. 数据源配置表
        if (!$schema->hasTable('saas_db_setting')) {
            $schema->create('saas_db_setting', function (Blueprint $table) {
                $table->id('id')->comment('雪花ID');
                $table->string('name', 100)->comment('数据源名称');
                $table->string('database', 64)->comment('数据库名称');
                $table->string('host', 255)->default('127.0.0.1')->comment('数据库主机');
                $table->integer('port')->default(3306)->comment('端口');
                $table->string('username', 64)->comment('用户名');
                $table->string('password', 255)->default('')->comment('密码(加密存储)');
                $table->string('prefix', 32)->default('')->comment('表前缀');
                $table->string('driver', 16)->default('mysql')->comment('驱动类型: mysql/pgsql/sqlite');
                $table->string('charset', 20)->default('utf8mb4')->comment('字符集');
                $table->string('collation', 50)->nullable()->comment('排序规则');
                $table->boolean('enabled')->default(false)->comment('是否启用: 0-禁用 1-启用');
                $table->boolean('is_default')->default(false)->comment('是否默认');
                $table->string('description', 255)->default('')->comment('描述');
                $table->integer('sort')->default(0)->comment('排序');
                $table->json('extra_config')->nullable()->comment('额外配置');
                $table->integer('last_test_time')->nullable()->comment('最后测试时间戳');
                $table->boolean('last_test_result')->nullable()->comment('最后测试结果: 1-成功 0-失败');
                $table->bigInteger('created_at')->nullable()->comment('创建时间');
                $table->bigInteger('updated_at')->nullable()->comment('更新时间');
                $table->bigInteger('deleted_at')->nullable()->comment('删除时间');

                $table->unique(['database', 'deleted_at'], 'uk_database');
                $table->index('enabled', 'idx_enabled');
                $table->index('driver', 'idx_driver');
                $table->index('is_default', 'idx_is_default');
            });
        }

        // 4. 权限定义表
        if (!$schema->hasTable('saas_permission')) {
            $schema->create('saas_permission', function (Blueprint $table) {
                $table->id('id')->comment('雪花ID');
                $table->string('permission_key', 100)->unique('uk_permission_key')->comment('权限标识(如 module.crm, field.order.price, api.export)');
                $table->string('permission_name', 100)->comment('权限名称');
                $table->string('permission_type', 20)->comment('权限类型: module-模块 field-字段 api-接口 feature-特性');
                $table->json('config')->nullable()->comment('权限配置(限制参数等JSON)');
                $table->integer('sort')->default(0)->comment('排序');
                $table->bigInteger('created_at')->nullable()->comment('创建时间');
                $table->bigInteger('updated_at')->nullable()->comment('更新时间');

                $table->index('permission_type', 'idx_permission_type');
            });
        }

        // 5. 套餐-权限关联表
        if (!$schema->hasTable('saas_subscription_permission')) {
            $schema->create('saas_subscription_permission', function (Blueprint $table) {
                $table->unsignedBigInteger('subscription_id')->comment('套餐ID');
                $table->unsignedBigInteger('permission_id')->comment('权限ID');

                $table->primary(['subscription_id', 'permission_id'], 'pk_sub_permission');
                $table->index('subscription_id', 'idx_subscription_id');
                $table->index('permission_id', 'idx_permission_id');
            });
        }

        // 6. 租户-套餐订阅关联表
        if (!$schema->hasTable('saas_tenant_subscription')) {
            $schema->create('saas_tenant_subscription', function (Blueprint $table) {
                $table->unsignedBigInteger('tenant_id')->comment('租户ID');
                $table->unsignedBigInteger('subscription_id')->comment('套餐ID');

                $table->primary(['tenant_id', 'subscription_id'], 'pk_tenant_subscription');
                $table->index('tenant_id', 'idx_tenant_id');
                $table->index('subscription_id', 'idx_subscription_id');
            });
        }

        // 7. 租户操作日志表
        if (!$schema->hasTable('saas_tenant_log')) {
            $schema->create('saas_tenant_log', function (Blueprint $table) {
                $table->id('id')->comment('雪花ID');
                $table->unsignedBigInteger('tenant_id')->nullable()->comment('租户ID');
                $table->unsignedBigInteger('admin_id')->nullable()->comment('管理员ID');
                $table->string('admin_name', 50)->nullable()->comment('管理员名称');
                $table->string('action', 50)->comment('操作类型: create/update/delete/suspend/active/renew/change_subscription');
                $table->string('target_type', 50)->nullable()->comment('目标类型');
                $table->string('target_id', 100)->nullable()->comment('目标ID');
                $table->json('before_data')->nullable()->comment('变更前数据');
                $table->json('after_data')->nullable()->comment('变更后数据');
                $table->string('ip', 50)->nullable()->comment('IP地址');
                $table->string('user_agent', 500)->nullable()->comment('User-Agent');
                $table->string('result', 20)->default('success')->comment('结果: success/fail');
                $table->text('error_msg')->nullable()->comment('错误信息');
                $table->integer('created_at')->nullable()->comment('创建时间戳');

                $table->index('tenant_id', 'idx_tenant_id');
                $table->index('admin_id', 'idx_admin_id');
                $table->index('action', 'idx_action');
                $table->index('target_type', 'idx_target_type');
                $table->index('result', 'idx_result');
                $table->index('created_at', 'idx_created_at');
            });
        }

        // 9. 租户资源使用表
        if (!$schema->hasTable('saas_tenant_resource')) {
            $schema->create('saas_tenant_resource', function (Blueprint $table) {
                $table->id('id')->comment('雪花ID');
                $table->unsignedBigInteger('tenant_id')->comment('租户ID');
                $table->string('resource_type', 50)->comment('资源类型: users/storage/agents/api_calls');
                $table->bigInteger('used')->default(0)->comment('已使用量');
                $table->bigInteger('quota')->default(0)->comment('配额上限');
                $table->string('unit', 20)->default('count')->comment('单位: count/MB/GB/次');
                $table->integer('warning_threshold')->default(80)->comment('警告阈值%');
                $table->date('reset_date')->nullable()->comment('重置日期(周期性配额)');
                $table->integer('last_reset_at')->nullable()->comment('上次重置时间戳');
                $table->bigInteger('created_at')->nullable()->comment('创建时间');
                $table->bigInteger('updated_at')->nullable()->comment('更新时间');
                $table->bigInteger('deleted_at')->nullable()->comment('删除时间');

                $table->unique(['tenant_id', 'resource_type'], 'uk_tenant_resource');
                $table->index('tenant_id', 'idx_tenant_id');
                $table->index('resource_type', 'idx_resource_type');
            });
        }

        // 10. 平台队列消息表
        if (!$schema->hasTable('saas_queue_message')) {
            $schema->create('saas_queue_message', function (Blueprint $table) {
                $table->id('id')->comment('雪花ID');
                $table->string('queue_name', 100)->comment('队列名称');
                $table->string('message_id', 64)->nullable()->comment('消息ID');
                $table->string('status', 20)->default('pending')->comment('状态: pending-待处理 processing-处理中 success-成功 failed-失败 dead-死信');
                $table->string('type', 50)->nullable()->comment('消息类型');
                $table->string('topic', 100)->nullable()->comment('主题');
                $table->json('payload')->nullable()->comment('消息内容');
                $table->json('result')->nullable()->comment('处理结果');
                $table->text('error_msg')->nullable()->comment('错误信息');
                $table->integer('retry_count')->default(0)->comment('重试次数');
                $table->integer('max_retry')->default(3)->comment('最大重试次数');
                $table->integer('created_at')->nullable()->comment('创建时间戳');
                $table->integer('processed_at')->nullable()->comment('处理完成时间戳');
                $table->integer('updated_at')->nullable()->comment('更新时间戳');

                $table->index('queue_name', 'idx_queue_name');
                $table->index('status', 'idx_status');
                $table->index('type', 'idx_type');
                $table->index('created_at', 'idx_created_at');
            });
        }

        // 11. 菜单模板表
        if (!$schema->hasTable('saas_template_menu')) {
            $schema->create('saas_template_menu', function (Blueprint $table) {
                $table->unsignedBigInteger('id')->primary()->comment('雪花ID');
                $table->bigInteger('pid')->default(0)->comment('父ID');
                $table->string('app', 32)->default('admin')->comment('应用编码');
                $table->string('source', 50)->default('template')->comment('菜单来源');
                $table->string('title', 64)->comment('菜单名称');
                $table->string('code', 64)->nullable()->comment('唯一编码');
                $table->string('level', 255)->nullable()->comment('父ID集合');
                $table->integer('type')->nullable()->comment('菜单类型: 1目录 2菜单 3按钮 4接口 5内链 6外链');
                $table->bigInteger('sort')->default(999)->comment('排序');
                $table->string('path', 100)->nullable()->comment('路由地址');
                $table->string('component', 100)->nullable()->comment('组件地址');
                $table->string('redirect', 255)->nullable()->comment('重定向');
                $table->string('icon', 64)->nullable()->comment('菜单图标');
                $table->tinyInteger('is_show')->default(1)->comment('是否显示: 0否 1是');
                $table->tinyInteger('is_link')->default(0)->comment('是否外链: 0否 1是');
                $table->longText('link_url')->nullable()->comment('外部链接地址');
                $table->tinyInteger('enabled')->default(1)->comment('状态: 1正常 0停用');
                $table->integer('open_type')->default(0)->comment('是否外链: 1是 0否');
                $table->tinyInteger('is_cache')->default(0)->comment('是否缓存: 1是 0否');
                $table->tinyInteger('is_sync')->default(1)->comment('是否同步');
                $table->tinyInteger('is_affix')->default(0)->comment('是否固定tags无法关闭');
                $table->tinyInteger('is_global')->default(0)->comment('是否全局公共菜单');
                $table->string('variable', 500)->nullable()->comment('额外参数JSON');
                $table->string('methods', 10)->default('get')->comment('请求方法');
                $table->tinyInteger('is_frame')->nullable()->comment('是否外链');
                $table->bigInteger('created_at')->nullable()->comment('创建时间');
                $table->bigInteger('created_by')->nullable()->comment('创建用户');
                $table->bigInteger('updated_at')->nullable()->comment('更新时间');
                $table->bigInteger('updated_by')->nullable()->comment('更新用户');
                $table->bigInteger('deleted_at')->nullable()->comment('删除时间');

                $table->index('code', 'idx_code');
                $table->index('app', 'idx_app');
                $table->index('pid', 'idx_pid');
                $table->index('enabled', 'idx_enabled');
                $table->index('type', 'idx_type');
            });
        }

        // 12. 配置模板表
        if (!$schema->hasTable('saas_template_config')) {
            $schema->create('saas_template_config', function (Blueprint $table) {
                $table->unsignedBigInteger('id')->primary()->comment('雪花ID');
                $table->string('group_code', 64)->nullable()->comment('分组编码');
                $table->string('code', 64)->comment('唯一编码');
                $table->string('name', 64)->comment('配置名称');
                $table->longText('content')->nullable()->comment('配置内容');
                $table->tinyInteger('is_sys')->default(0)->comment('是否系统');
                $table->tinyInteger('enabled')->default(1)->comment('是否启用');
                $table->integer('sort')->default(0)->comment('排序');
                $table->longText('remark')->nullable()->comment('备注');
                $table->bigInteger('created_at')->nullable()->comment('创建时间');
                $table->bigInteger('created_by')->nullable()->comment('创建用户');
                $table->bigInteger('updated_at')->nullable()->comment('更新时间');
                $table->bigInteger('updated_by')->nullable()->comment('更新用户');
                $table->bigInteger('deleted_at')->nullable()->comment('删除时间');

                $table->unique(['group_code', 'code'], 'uk_group_code');
                $table->index('enabled', 'idx_enabled');
            });
        }

        // 13. 前端菜单模板表
        if (!$schema->hasTable('saas_template_web_menu')) {
            $schema->create('saas_template_web_menu', function (Blueprint $table) {
                $table->unsignedBigInteger('id')->primary()->comment('雪花ID');
                $table->bigInteger('pid')->default(0)->comment('父ID');
                $table->string('app', 32)->default('web')->comment('应用编码');
                $table->string('category', 32)->default('1')->comment('菜单分类');
                $table->string('source', 50)->default('template')->comment('菜单来源');
                $table->string('code', 64)->nullable()->comment('唯一编码');
                $table->tinyInteger('is_public')->default(0)->comment('是否公开菜单');
                $table->tinyInteger('is_no_auth')->default(0)->comment('是否跳过权限校验');
                $table->string('name', 64)->comment('菜单名称');
                $table->string('url', 255)->nullable()->comment('链接地址');
                $table->string('icon', 64)->nullable()->comment('菜单图标');
                $table->integer('level')->default(1)->comment('菜单级别');
                $table->integer('type')->default(1)->comment('菜单类型');
                $table->bigInteger('sort')->default(999)->comment('排序');
                $table->integer('target')->default(1)->comment('打开方式');
                $table->json('extra')->nullable()->comment('扩展数据');
                $table->tinyInteger('is_show')->default(1)->comment('是否显示');
                $table->tinyInteger('enabled')->default(1)->comment('状态');
                $table->bigInteger('created_at')->nullable()->comment('创建时间');
                $table->bigInteger('created_by')->nullable()->comment('创建用户');
                $table->bigInteger('updated_at')->nullable()->comment('更新时间');
                $table->bigInteger('updated_by')->nullable()->comment('更新用户');
                $table->bigInteger('deleted_at')->nullable()->comment('删除时间');

                $table->index('code', 'idx_code');
                $table->index('app', 'idx_app');
                $table->index('pid', 'idx_pid');
                $table->index('enabled', 'idx_enabled');
                $table->index('category', 'idx_category');
            });
        }

        // 14. 字典模板表
        if (!$schema->hasTable('saas_template_dict')) {
            $schema->create('saas_template_dict', function (Blueprint $table) {
                $table->unsignedBigInteger('id')->primary()->comment('雪花ID');
                $table->string('app', 32)->default('admin')->comment('应用编码');
                $table->string('group_code', 50)->nullable()->comment('字典分组');
                $table->string('name', 50)->nullable()->comment('字典名称');
                $table->string('code', 100)->nullable()->comment('字典标识');
                $table->bigInteger('sort')->default(0)->comment('排序');
                $table->smallInteger('data_type')->default(1)->comment('数据类型: 1字符串 2数字');
                $table->longText('description')->nullable()->comment('描述');
                $table->smallInteger('enabled')->default(1)->comment('状态: 1正常 0停用');
                $table->bigInteger('created_at')->nullable()->comment('创建时间');
                $table->bigInteger('created_by')->nullable()->comment('创建用户');
                $table->bigInteger('updated_at')->nullable()->comment('更新时间');
                $table->bigInteger('updated_by')->nullable()->comment('更新用户');
                $table->bigInteger('deleted_at')->nullable()->comment('删除时间');

                $table->index('code', 'idx_code');
                $table->index('app', 'idx_app');
                $table->index('group_code', 'idx_group_code');
                $table->index('enabled', 'idx_enabled');
            });
        }

        // 15. 字典模板项表
        if (!$schema->hasTable('saas_template_dict_item')) {
            $schema->create('saas_template_dict_item', function (Blueprint $table) {
                $table->unsignedBigInteger('id')->primary()->comment('雪花ID');
                $table->bigInteger('dict_template_id')->default(0)->comment('字典模板ID');
                $table->string('label', 50)->nullable()->comment('字典标签');
                $table->string('value', 100)->nullable()->comment('字典值');
                $table->string('code', 100)->nullable()->comment('字典标识');
                $table->string('color', 50)->nullable()->comment('tag颜色');
                $table->string('other_class', 50)->nullable()->comment('扩展样式');
                $table->smallInteger('sort')->default(0)->comment('排序');
                $table->smallInteger('enabled')->default(1)->comment('状态: 1正常 0停用');
                $table->longText('remark')->nullable()->comment('备注');
                $table->bigInteger('created_at')->nullable()->comment('创建时间');
                $table->bigInteger('created_by')->nullable()->comment('创建用户');
                $table->bigInteger('updated_at')->nullable()->comment('更新时间');
                $table->bigInteger('updated_by')->nullable()->comment('更新用户');
                $table->bigInteger('deleted_at')->nullable()->comment('删除时间');

                $table->index('dict_template_id', 'idx_dict_template_id');
                $table->index('code', 'idx_code');
                $table->index('enabled', 'idx_enabled');
            });
        }

        // 初始化套餐数据
        $this->initSubscriptionData();
    }

    public function down(Builder $schema): void
    {
        $this->schema = $schema;
        $schema->dropIfExists('saas_template_dict_item');
        $schema->dropIfExists('saas_template_dict');
        $schema->dropIfExists('saas_template_web_menu');
        $schema->dropIfExists('saas_template_config');
        $schema->dropIfExists('saas_template_menu');
        $schema->dropIfExists('saas_tenant_resource');
        $schema->dropIfExists('saas_tenant_log');
        $schema->dropIfExists('saas_queue_message');
        $schema->dropIfExists('saas_tenant_subscription');
        $schema->dropIfExists('saas_subscription_permission');
        $schema->dropIfExists('saas_permission');
        $schema->dropIfExists('saas_db_setting');
        $schema->dropIfExists('saas_tenant');
        $schema->dropIfExists('saas_subscription');
    }

    /**
     * 初始化套餐数据
     */
    protected function initSubscriptionData(): void
    {
        $now = time();

        $subscriptions = [
            [
                'id'          => 1,
                'code'        => 'free',
                'name'        => '免费版',
                'description' => '适合个人或小团队入门使用',
                'price'       => 0.00,
                'price_year'  => 0.00,
                'max_users'   => 5,
                'max_storage' => 1024,
                'max_agents'  => 2,
                'rate_limit'  => 100,
                'sort'        => 0,
                'is_trial'    => false,
                'trial_days'  => 0,
                'status'      => 'active',
                'is_default'  => true,
                'created_at'  => $now,
                'updated_at'  => $now,
            ],
            [
                'id'          => 2,
                'code'        => 'basic',
                'name'        => '基础版',
                'description' => '适合小型企业日常办公使用',
                'price'       => 99.00,
                'price_year'  => 990.00,
                'max_users'   => 50,
                'max_storage' => 10240,
                'max_agents'  => 10,
                'rate_limit'  => 1000,
                'sort'        => 10,
                'is_trial'    => true,
                'trial_days'  => 7,
                'status'      => 'active',
                'is_default'  => false,
                'created_at'  => $now,
                'updated_at'  => $now,
            ],
            [
                'id'          => 3,
                'code'        => 'professional',
                'name'        => '专业版',
                'description' => '适合中型企业，支持更多高级功能',
                'price'       => 299.00,
                'price_year'  => 2990.00,
                'max_users'   => 200,
                'max_storage' => 51200,
                'max_agents'  => 50,
                'rate_limit'  => 5000,
                'sort'        => 20,
                'is_trial'    => true,
                'trial_days'  => 15,
                'status'      => 'active',
                'is_default'  => false,
                'created_at'  => $now,
                'updated_at'  => $now,
            ],
            [
                'id'          => 4,
                'code'        => 'enterprise',
                'name'        => '企业版',
                'description' => '适合大型企业，全面功能支持',
                'price'       => 999.00,
                'price_year'  => 9990.00,
                'max_users'   => 0,
                'max_storage' => 0,
                'max_agents'  => 0,
                'rate_limit'  => 0,
                'sort'        => 30,
                'is_trial'    => true,
                'trial_days'  => 30,
                'status'      => 'active',
                'is_default'  => false,
                'created_at'  => $now,
                'updated_at'  => $now,
            ],
        ];

        $existing = $this->schema->getConnection()->table('saas_subscription')->count();
        if ($existing === 0) {
            $this->schema->getConnection()->table('saas_subscription')->insert($subscriptions);
        }
    }

};
