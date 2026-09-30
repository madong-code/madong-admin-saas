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
 * 消息中心：分类/定义/模板补充来源标识，模板补充同步稳定键
 *
 * - source: system=框架内置(resource/data/message) plugin:{name}=插件导入 user=后台自建
 *   插件卸载时据此精确清理，不会误删系统数据与后台自建数据。
 * - sys_message_template.key: 模板在数据同步时的稳定幂等键，与 type 组合唯一。
 *   存量数据该列为 NULL，MySQL 唯一索引允许多个 NULL，不冲突。
 */

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

return new class
{
    public function up(Builder $schema): void
    {
        if ($schema->hasTable('sys_message_category') && !$schema->hasColumn('sys_message_category', 'source')) {
            $schema->table('sys_message_category', function (Blueprint $table) {
                $table->string('source', 50)
                    ->default('system')
                    ->after('is_system')
                    ->comment('数据来源: system=框架 plugin:{name}=插件 user=后台自建');
                $table->index('source', 'idx_msg_category_source');
            });
        }

        if ($schema->hasTable('sys_message_definition') && !$schema->hasColumn('sys_message_definition', 'source')) {
            $schema->table('sys_message_definition', function (Blueprint $table) {
                $table->string('source', 50)
                    ->default('system')
                    ->after('is_system')
                    ->comment('数据来源: system=框架 plugin:{name}=插件 user=后台自建');
                $table->index('source', 'idx_msg_definition_source');
            });
        }

        if ($schema->hasTable('sys_message_template') && !$schema->hasColumn('sys_message_template', 'source')) {
            $schema->table('sys_message_template', function (Blueprint $table) {
                $table->string('source', 50)
                    ->default('system')
                    ->after('is_system')
                    ->comment('数据来源: system=框架 plugin:{name}=插件 user=后台自建');
                $table->index('source', 'idx_msg_template_source');
            });
        }

        if ($schema->hasTable('sys_message_template') && !$schema->hasColumn('sys_message_template', 'key')) {
            $schema->table('sys_message_template', function (Blueprint $table) {
                $table->string('key', 100)
                    ->nullable()
                    ->after('type')
                    ->comment('模板稳定标识(与 type 组合唯一, 用于数据同步幂等)');
                $table->unique(['type', 'key'], 'uk_msg_template_type_key');
            });
        }
    }

    public function down(Builder $schema): void
    {
        if ($schema->hasTable('sys_message_template') && $schema->hasColumn('sys_message_template', 'key')) {
            $schema->table('sys_message_template', function (Blueprint $table) {
                $table->dropUnique('uk_msg_template_type_key');
                $table->dropColumn('key');
            });
        }

        if ($schema->hasTable('sys_message_template') && $schema->hasColumn('sys_message_template', 'source')) {
            $schema->table('sys_message_template', function (Blueprint $table) {
                $table->dropIndex('idx_msg_template_source');
                $table->dropColumn('source');
            });
        }

        if ($schema->hasTable('sys_message_definition') && $schema->hasColumn('sys_message_definition', 'source')) {
            $schema->table('sys_message_definition', function (Blueprint $table) {
                $table->dropIndex('idx_msg_definition_source');
                $table->dropColumn('source');
            });
        }

        if ($schema->hasTable('sys_message_category') && $schema->hasColumn('sys_message_category', 'source')) {
            $schema->table('sys_message_category', function (Blueprint $table) {
                $table->dropIndex('idx_msg_category_source');
                $table->dropColumn('source');
            });
        }
    }
};
