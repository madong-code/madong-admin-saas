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
 * 吸收标准版（madong-single 配套后端）升级列，对齐共用仓库基线
 *
 * 来源:
 *   标准版 2026_09_24_000002_add_upgrade_columns.php
 *   标准版 2026_10_10_000001_alter_sys_plugin_support_app.php
 *
 * 内容:
 * - member.last_active_time: 任意有效 API 请求节流更新（每用户 5 分钟最多一次），
 *   与 last_login_time（仅登录时更新）语义分离，活跃用户列表按本字段排序。
 *   saas 建表脚本（2026_07_01_000003_create_member_tables）未包含本列。
 * - sys_upload.source: 附件来源（default=系统默认，plugin:{插件编码}=插件上传），
 *   插件卸载按 source 精确回收上传资源，不再依赖路径前缀 LIKE 反推。
 *   saas 建表脚本（2026_07_01_000001_create_system_tables）未包含本列；
 *   sys_upload.space 已由 2026_09_27_000002_add_space_to_upload_table 覆盖，此处不重复。
 * - sys_plugin.support_app: 语义为插件前置依赖声明（多个逗号分隔），
 *   varchar(32) 无法容纳多值，拓宽至 varchar(255)。
 *
 * 兼容:
 *   全部操作幂等（先 hasColumn/hasTable 判断），存量记录默认值与标准版一致；
 *   对已执行过标准版迁移的共用库为空操作。
 */

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

return new class
{
    public function up(Builder $schema): void
    {
        // member.last_active_time
        if ($schema->hasTable('member') && !$schema->hasColumn('member', 'last_active_time')) {
            $schema->table('member', function (Blueprint $table) {
                $table->integer('last_active_time')
                    ->nullable()
                    ->comment('最后活跃时间戳(任意有效请求节流更新)')
                    ->after('last_login_time');
                $table->index('last_active_time', 'idx_member_last_active_time');
            });
        }

        // sys_upload.source
        if ($schema->hasTable('sys_upload') && !$schema->hasColumn('sys_upload', 'source')) {
            $schema->table('sys_upload', function (Blueprint $table) {
                $table->string('source', 64)
                    ->default('default')
                    ->comment('来源归属:default系统 plugin:{插件编码}')
                    ->after('platform');
                $table->index('source', 'idx_sys_upload_source');
            });
        }

        // sys_plugin.support_app 拓宽（标准版语义修正：前置依赖声明，varchar(32) 容纳不了多值）
        if ($schema->hasTable('sys_plugin') && $schema->hasColumn('sys_plugin', 'support_app')) {
            $schema->table('sys_plugin', function (Blueprint $table) {
                $table->string('support_app', 255)
                    ->default('admin')
                    ->comment('前置插件依赖（插件代码，多个逗号分隔）')
                    ->change();
            });
        }
    }

    public function down(Builder $schema): void
    {
        if ($schema->hasTable('sys_plugin') && $schema->hasColumn('sys_plugin', 'support_app')) {
            $schema->table('sys_plugin', function (Blueprint $table) {
                $table->string('support_app', 32)
                    ->default('admin')
                    ->comment('支持的终端')
                    ->change();
            });
        }

        if ($schema->hasTable('sys_upload') && $schema->hasColumn('sys_upload', 'source')) {
            $schema->table('sys_upload', function (Blueprint $table) {
                $table->dropIndex('idx_sys_upload_source');
                $table->dropColumn('source');
            });
        }

        if ($schema->hasTable('member') && $schema->hasColumn('member', 'last_active_time')) {
            $schema->table('member', function (Blueprint $table) {
                $table->dropIndex('idx_member_last_active_time');
                $table->dropColumn('last_active_time');
            });
        }
    }
};
