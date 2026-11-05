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
 * 消息三表按租户隔离：唯一索引改为含 tenant_id 的组合键
 *
 * 背景:
 *   sys_message_category 等表只有 key 单列唯一（uk_key），而消息数据需要支持
 *   "每个租户一份"（字段隔离 tenant_id=T，库隔离落租户库）。租户安装插件时写入
 *   (tenant_id=T, key=X) 会与平台行/其他租户行冲突，报 Duplicate entry ... for key 'uk_key'。
 *
 * 处理:
 *   1. tenant_id 的 NULL 归一为 0（0=平台/单租户默认数据），并把列改为 NOT NULL DEFAULT 0；
 *      原因：MySQL 复合唯一索引对含 NULL 的行不做去重，必须用非空值承载平台数据。
 *   2. 按新唯一键去重（同组保留 source=system > source=user > id 最小），
 *      并同步清理被删节点在 sys_message_definition_template 中的关联。
 *   3. 删除旧唯一索引，创建含 tenant_id 的组合唯一索引。
 */

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;
use support\Log;

return new class
{
    /** 需要归一的表（不含表前缀） */
    private const TENANT_TABLES = [
        'sys_message_category',
        'sys_message_definition',
        'sys_message_template',
        'sys_message_definition_template',
    ];

    public function up(Builder $schema): void
    {
        $connection = $schema->getConnection();
        $prefix     = $connection->getTablePrefix();

        // 1. tenant_id NULL -> 0
        foreach (self::TENANT_TABLES as $table) {
            if (!$schema->hasTable($table) || !$schema->hasColumn($table, 'tenant_id')) {
                continue;
            }
            $connection->table($table)->whereNull('tenant_id')->update(['tenant_id' => 0]);
        }

        // 2. 去重（必须在新唯一索引创建前完成）
        $this->dedupe($schema, 'sys_message_category', ['tenant_id', 'key'], 'category');
        $this->dedupe($schema, 'sys_message_definition', ['tenant_id', 'category_id', 'key'], 'definition');
        $this->dedupe($schema, 'sys_message_template', ['tenant_id', 'type', 'key'], 'template');

        // 3. tenant_id 改 NOT NULL DEFAULT 0
        foreach (self::TENANT_TABLES as $table) {
            if (!$schema->hasTable($table) || !$schema->hasColumn($table, 'tenant_id')) {
                continue;
            }
            try {
                $connection->statement(sprintf(
                    'ALTER TABLE `%s%s` MODIFY `tenant_id` BIGINT NOT NULL DEFAULT 0 COMMENT %s',
                    $prefix,
                    $table,
                    "'租户ID: 0=平台/单租户'"
                ));
            } catch (\Throwable $e) {
                $this->warn('normalize tenant_id column failed', [
                    'table' => $table,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // 4. 索引切换
        $this->swapIndex($schema, 'sys_message_category', 'uk_key', 'uk_tenant_key', ['tenant_id', 'key'], 'idx_msg_category_key');
        $this->swapIndex($schema, 'sys_message_definition', 'uk_category_definition', 'uk_tenant_category_definition', ['tenant_id', 'category_id', 'key'], 'idx_msg_definition_key');
        $this->swapIndex($schema, 'sys_message_template', 'uk_msg_template_type_key', 'uk_tenant_type_key', ['tenant_id', 'type', 'key'], 'idx_msg_template_key');
    }

    public function down(Builder $schema): void
    {
        // 数据归一/去重不可逆，仅回滚索引结构
        $this->dropIndex($schema, 'sys_message_category', 'uk_tenant_key');
        $this->dropIndex($schema, 'sys_message_definition', 'uk_tenant_category_definition');
        $this->dropIndex($schema, 'sys_message_template', 'uk_tenant_type_key');

        $this->dropIndex($schema, 'sys_message_category', 'idx_msg_category_key');
        $this->dropIndex($schema, 'sys_message_definition', 'idx_msg_definition_key');
        $this->dropIndex($schema, 'sys_message_template', 'idx_msg_template_key');
    }

    /**
     * 按分组列去重：同组保留优先行，删除其余行及其关联
     *
     * @param array<int, string> $groupColumns
     */
    private function dedupe(Builder $schema, string $table, array $groupColumns, string $label): void
    {
        if (!$schema->hasTable($table)) {
            return;
        }

        $connection = $schema->getConnection();
        $columns    = array_merge($groupColumns, ['id']);

        foreach ($groupColumns as $column) {
            if (!$schema->hasColumn($table, $column)) {
                return;
            }
        }

        // source 列在旧版本可能尚未添加（2026_09_27 迁移），缺失时按空值参与优先级计算
        if ($schema->hasColumn($table, 'source')) {
            $columns[] = 'source';
        }

        $rows   = $connection->table($table)->select($columns)->get();
        $groups = [];

        foreach ($rows as $row) {
            $parts = [];
            foreach ($groupColumns as $column) {
                $parts[] = (string)($row->{$column} ?? '');
            }

            // 组合键含空值（如历史模板 key 为 NULL）时不参与去重：
            // MySQL 唯一索引允许多个 NULL，且它们本就不是同一业务节点。
            if (in_array('', $parts, true)) {
                continue;
            }

            $groups[implode("\x1F", $parts)][] = $row;
        }

        $deleted = 0;
        foreach ($groups as $items) {
            if (count($items) < 2) {
                continue;
            }

            usort($items, function ($a, $b) {
                $priority = $this->sourcePriority((string)($a->source ?? '')) <=> $this->sourcePriority((string)($b->source ?? ''));
                return $priority !== 0 ? $priority : ((int)$a->id <=> (int)$b->id);
            });

            array_shift($items);
            $ids = array_map(static fn($item) => $item->id, $items);

            $this->deleteDependents($schema, $table, $ids);
            $connection->table($table)->whereIn('id', $ids)->delete();
            $deleted += count($ids);
        }

        if ($deleted > 0) {
            $this->warn('message table deduplicated', [
                'table'   => $table,
                'deleted' => $deleted,
                'label'   => $label,
            ]);
        }
    }

    /**
     * 删除依赖行，避免留下孤儿定义/关联
     */
    private function deleteDependents(Builder $schema, string $table, array $ids): void
    {
        if ($ids === []) {
            return;
        }

        $connection = $schema->getConnection();

        if ($table === 'sys_message_category' && $schema->hasTable('sys_message_definition')) {
            $definitionIds = $connection->table('sys_message_definition')
                ->whereIn('category_id', $ids)
                ->pluck('id')->all();

            if ($definitionIds !== [] && $schema->hasTable('sys_message_definition_template')) {
                $connection->table('sys_message_definition_template')
                    ->whereIn('definition_id', $definitionIds)
                    ->delete();
            }

            if ($definitionIds !== []) {
                $connection->table('sys_message_definition')->whereIn('id', $definitionIds)->delete();
            }
            return;
        }

        if ($table === 'sys_message_definition' && $schema->hasTable('sys_message_definition_template')) {
            $connection->table('sys_message_definition_template')
                ->whereIn('definition_id', $ids)
                ->delete();
            return;
        }

        if ($table === 'sys_message_template' && $schema->hasTable('sys_message_definition_template')) {
            $connection->table('sys_message_definition_template')
                ->whereIn('template_id', $ids)
                ->delete();
        }
    }

    /**
     * 删除旧唯一索引并创建含 tenant_id 的新唯一索引
     *
     * @param array<int, string> $columns
     */
    private function swapIndex(
        Builder $schema,
        string $table,
        string $oldIndex,
        string $newIndex,
        array $columns,
        string $lookupIndex
    ): void {
        if (!$schema->hasTable($table)) {
            return;
        }

        $this->dropIndex($schema, $table, $oldIndex);

        foreach ($columns as $column) {
            if (!$schema->hasColumn($table, $column)) {
                return;
            }
        }

        if (!$this->hasIndex($schema, $table, $newIndex)) {
            try {
                $schema->table($table, function (Blueprint $blueprint) use ($columns, $newIndex) {
                    $blueprint->unique($columns, $newIndex);
                });
            } catch (\Throwable $e) {
                $this->warn('create unique index failed', [
                    'table'   => $table,
                    'index'   => $newIndex,
                    'error'   => $e->getMessage(),
                ]);
            }
        }

        // 保留一个普通索引，供按 key 反查（如 NotifyService）
        if (!$this->hasIndex($schema, $table, $lookupIndex)) {
            try {
                $schema->table($table, function (Blueprint $blueprint) use ($lookupIndex) {
                    $blueprint->index('key', $lookupIndex);
                });
            } catch (\Throwable $e) {
                // 普通索引失败不影响唯一约束
            }
        }
    }

    private function dropIndex(Builder $schema, string $table, string $index): void
    {
        if (!$schema->hasTable($table) || !$this->hasIndex($schema, $table, $index)) {
            return;
        }

        try {
            $schema->table($table, function (Blueprint $blueprint) use ($index) {
                $blueprint->dropUnique($index);
            });
        } catch (\Throwable $e) {
            // 兼容普通索引
            try {
                $schema->table($table, function (Blueprint $blueprint) use ($index) {
                    $blueprint->dropIndex($index);
                });
            } catch (\Throwable $ignore) {
                $this->warn('drop index failed', [
                    'table' => $table,
                    'index' => $index,
                    'error' => $ignore->getMessage(),
                ]);
            }
        }
    }

    private function hasIndex(Builder $schema, string $table, string $index): bool
    {
        try {
            $prefix = $schema->getConnection()->getTablePrefix();
            $rows   = $schema->getConnection()->select("SHOW INDEX FROM `{$prefix}{$table}`");
            foreach ($rows as $row) {
                if ((string)($row->Key_name ?? '') === $index) {
                    return true;
                }
            }
        } catch (\Throwable $e) {
            return false;
        }

        return false;
    }

    private function sourcePriority(string $source): int
    {
        return match (true) {
            $source === 'system'          => 0,
            $source === 'user'            => 1,
            str_starts_with($source, 'plugin:') => 2,
            default                       => 3,
        };
    }

    private function warn(string $message, array $context = []): void
    {
        try {
            Log::warning('[message-tenant-migration] ' . $message, $context);
        } catch (\Throwable $e) {
            // 日志失败忽略
        }
    }
};
