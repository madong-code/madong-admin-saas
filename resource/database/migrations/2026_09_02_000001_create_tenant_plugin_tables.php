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

/**
 * 租户插件管理表（合并迁移）
 *
 * 包含表:
 *   1. saas_tenant_plugin           - 租户插件授权治理表(auth_status/is_purchased/purchased_at/expires_at/
 *                                     allow_upgrade/ignored_version + 运行态过渡镜像列)
 *   2. saas_tenant_plugin_install   - 租户插件安装运行态表(status/installed_at/sync_status/isolation_mode/
 *                                     version/deprecated_version/config, 1:1 与授权表同 id 关联)
 *   3. saas_tenant_plugin_sync_jobs - 插件批量同步任务审计表
 *
 * 命名规范:
 *   统一 saas_ 域前缀 + 模块语义(saas_tenant_plugin_*), md_ 由数据库连接前缀自动补齐,
 *   迁移内不写 md_ 前缀。
 *
 * 说明:
 *   本迁移合并了原 20260901000001(建表)、20260902000001(升级治理字段) 与
 *   20260902000002(拆分 saas_tenant_plugin_install 运行态), 一次建到最终结构。
 *   - 授权治理表暂保留运行字段作为过渡镜像列: 代码层 TenantPlugin 模型通过
 *     install 关系代理运行态字段(fallback 到本表旧列), Orchestrator 安装/升级
 *     对两张表双写; 待所有读写确认只走 install 表后可再清理镜像列。
 *   - 迁移内对已执行过旧两步迁移的库幂等: hasTable 跳过建表 + updateOrInsert 回填。
 */

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

return new class
{
    private Builder $schema;

    public function up(Builder $schema): void
    {
        $this->schema = $schema;
        $conn = $schema->getConnection();

        // 1. 租户插件授权治理表(含运行态过渡镜像列)
        if (!$schema->hasTable('saas_tenant_plugin')) {
            $schema->create('saas_tenant_plugin', function (Blueprint $table) {
                $table->bigInteger('id')->primary()->comment('主键(Snowflake)');
                $table->bigInteger('tenant_id')->comment('租户ID(雪花ID)');
                $table->string('tenant_name', 100)->nullable()->comment('租户名称(冗余)');
                $table->string('plugin_key', 64)->comment('插件唯一标识');
                $table->string('auth_status', 16)->default('none')->comment('授权状态: none-未授权 authorized-已授权 trial-试用');
                $table->tinyInteger('status')->default(1)->comment('状态: 1启用 0停用(过渡镜像列, 运行态以 install 表为准)');
                $table->tinyInteger('is_purchased')->default(0)->comment('预留: 0=未购买/未授权 1=已购买/已授权');
                $table->bigInteger('purchased_at')->nullable()->comment('预留: 购买/授权时间');
                $table->bigInteger('expires_at')->nullable()->comment('预留: 过期时间(null=永不过期)');
                $table->text('config')->nullable()->comment('预留: 租户级配置(JSON, 新逻辑走租户侧 sys_config)');
                $table->bigInteger('installed_at')->nullable()->comment('安装时间(过渡镜像列)');
                $table->string('sync_status', 16)->default('pending')->comment('pending|running|success|failed(过渡镜像列)');
                $table->string('isolation_mode', 16)->default('field')->comment('field|database 租户隔离模式(过渡镜像列)');
                $table->string('version', 32)->nullable()->default('1.0.0')->comment('租户侧安装版本(过渡镜像列)');
                $table->string('deprecated_version', 32)->nullable()->comment('菜单废弃时记录的旧版本(过渡镜像列)');
                $table->tinyInteger('allow_upgrade')->default(1)->comment('平台控制是否允许升级: 1允许 0禁止');
                $table->string('ignored_version', 32)->nullable()->comment('租户端忽略的升级版本');
                $table->bigInteger('created_at')->nullable()->comment('创建时间');
                $table->bigInteger('updated_at')->nullable()->comment('修改时间');

                $table->unique(['tenant_id', 'plugin_key'], 'uk_tenant_plugin');
                $table->index('tenant_id', 'idx_tenant_id');
                $table->index('plugin_key', 'idx_plugin_key');
                $table->index('auth_status', 'idx_auth_status');
                $table->index('sync_status', 'idx_sync_status');
                $table->index('isolation_mode', 'idx_isolation_mode');
            });
        }

        // 2. 租户插件安装运行态表
        if (!$schema->hasTable('saas_tenant_plugin_install')) {
            $schema->create('saas_tenant_plugin_install', function (Blueprint $table) {
                $table->bigInteger('id')->primary()->comment('主键(Snowflake, 与授权记录同 id)');
                $table->bigInteger('tenant_id')->comment('租户ID(雪花ID)');
                $table->string('tenant_name', 100)->nullable()->comment('租户名称(冗余)');
                $table->string('plugin_key', 64)->comment('插件唯一标识');
                $table->tinyInteger('status')->default(1)->comment('状态: 1启用 0停用');
                $table->bigInteger('installed_at')->nullable()->comment('安装时间');
                $table->string('sync_status', 16)->default('pending')->comment('pending|running|success|failed 同步状态');
                $table->string('isolation_mode', 16)->default('field')->comment('field|database 租户隔离模式');
                $table->string('version', 32)->nullable()->default('1.0.0')->comment('租户侧安装版本');
                $table->string('deprecated_version', 32)->nullable()->comment('菜单废弃时记录的旧版本');
                $table->text('config')->nullable()->comment('租户级配置(JSON)');
                $table->bigInteger('created_at')->nullable()->comment('创建时间');
                $table->bigInteger('updated_at')->nullable()->comment('修改时间');

                $table->unique(['tenant_id', 'plugin_key'], 'uk_tenant_plugin_install');
                $table->index('tenant_id', 'idx_install_tenant_id');
                $table->index('plugin_key', 'idx_install_plugin_key');
                $table->index('status', 'idx_install_status');
                $table->index('sync_status', 'idx_install_sync_status');
                $table->index('isolation_mode', 'idx_install_isolation_mode');
            });
        }

        // 3. 运行态数据回填: 授权表过渡镜像列 -> 运行态表(同 id/同 (tenant_id, plugin_key) 幂等)
        //    全新安装无存量数据自然跳过; 已执行过旧两步迁移的库同样幂等。
        if ($schema->hasTable('saas_tenant_plugin')
            && $schema->hasColumn('saas_tenant_plugin', 'sync_status')) {
            $records = $conn->table('saas_tenant_plugin')
                ->select([
                    'id', 'tenant_id', 'tenant_name', 'plugin_key',
                    'status', 'installed_at', 'sync_status', 'isolation_mode',
                    'version', 'deprecated_version', 'config', 'created_at', 'updated_at',
                ])
                ->get();

            foreach ($records as $r) {
                $conn->table('saas_tenant_plugin_install')->updateOrInsert(
                    ['tenant_id' => $r->tenant_id, 'plugin_key' => $r->plugin_key],
                    [
                        'id'               => $r->id,
                        'tenant_id'        => $r->tenant_id,
                        'tenant_name'      => $r->tenant_name,
                        'plugin_key'       => $r->plugin_key,
                        'status'           => $r->status ?? 1,
                        'installed_at'     => $r->installed_at,
                        'sync_status'      => $r->sync_status ?? 'pending',
                        'isolation_mode'   => $r->isolation_mode ?? 'field',
                        'version'          => $r->version ?? '1.0.0',
                        'deprecated_version' => $r->deprecated_version,
                        'config'           => $r->config,
                        'created_at'       => $r->created_at,
                        'updated_at'       => $r->updated_at,
                    ]
                );
            }
        }

        // 4. 插件同步任务审计表
        if (!$schema->hasTable('saas_tenant_plugin_sync_jobs')) {
            $schema->create('saas_tenant_plugin_sync_jobs', function (Blueprint $table) {
                $table->bigInteger('id')->primary()->comment('雪花ID');
                $table->string('plugin_key', 64)->comment('插件标识');
                $table->string('action', 16)->comment('install|update|uninstall|reinstall');
                $table->json('tenant_ids')->nullable()->comment('目标租户ID列表(JSON)');
                $table->tinyInteger('force')->default(0)->comment('是否强制级联: 0否 1是');
                $table->string('status', 16)->default('pending')->comment('pending|running|success|failed');
                $table->integer('progress_total')->default(0)->comment('总任务数');
                $table->integer('progress_done')->default(0)->comment('已完成数');
                $table->text('error')->nullable()->comment('错误信息');
                $table->bigInteger('operator_id')->nullable()->comment('操作人ID');
                $table->bigInteger('started_at')->nullable()->comment('开始时间(时间戳)');
                $table->bigInteger('finished_at')->nullable()->comment('结束时间(时间戳)');
                $table->bigInteger('created_at')->nullable()->comment('创建时间(时间戳)');
                $table->bigInteger('updated_at')->nullable()->comment('更新时间(时间戳)');

                $table->index(['plugin_key', 'status'], 'idx_plugin_status');
                $table->index('created_at', 'idx_created_at');
            });
        }
    }

    public function down(Builder $schema): void
    {
        $schema->dropIfExists('saas_tenant_plugin_sync_jobs');
        $schema->dropIfExists('saas_tenant_plugin_install');
        $schema->dropIfExists('saas_tenant_plugin');
    }
};
