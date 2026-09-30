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
namespace core\business\generator\file;

use core\business\generator\utils\TemplateRenderer;

/**
 * 迁移文件生成器
 * 负责生成数据库迁移 SQL 文件
 */
class MigrationGenerator extends AbstractFileGenerator
{
    private const TYPE_MAP = [
        'int'       => 'int(11)',
        'integer'   => 'int(11)',
        'tinyint'   => 'tinyint(4)',
        'smallint'  => 'smallint(6)',
        'mediumint' => 'mediumint(8)',
        'bigint'    => 'bigint(20)',
        'float'     => 'decimal(10,2)',
        'double'    => 'decimal(12,2)',
        'decimal'   => 'decimal(10,2)',
        'string'    => 'varchar(255)',
        'varchar'   => 'varchar(255)',
        'char'      => 'char(32)',
        'text'      => 'text',
        'mediumtext' => 'mediumtext',
        'longtext'  => 'longtext',
        'json'      => 'json',
        'date'      => 'date',
        'datetime'  => 'datetime',
        'timestamp' => 'timestamp',
        'boolean'   => 'tinyint(1)',
        'bool'      => 'tinyint(1)',
    ];

    public function __construct(array $config, ?TemplateRenderer $templateRenderer = null)
    {
        parent::__construct($config, $templateRenderer);
    }

    public function generateContent(): string
    {
        $tableName = $this->config['table_name'] ?? $this->config['package_name'] ?? 'default_table';
        $className = $this->config['class_name'] ?? 'DefaultModel';
        $tableComment = $this->config['table_comment'] ?? $this->config['class_name_comment'] ?? $className;
        $columns = $this->config['columns'] ?? [];
        $deleteConfig = $this->config['config'] ?? [];

        $fieldLines = [];
        $indexLines = [];
        $hasDeleteColumn = ($deleteConfig['is_delete'] ?? 0) == 1;
        $deleteColumnName = $deleteConfig['delete_column_name'] ?? 'deleted_at';

        foreach ($columns as $column) {
            $colName = $column['column_name'] ?? '';
            $colType = $column['column_type'] ?? 'varchar';
            $colComment = $column['column_comment'] ?? '';
            $isPk = ($column['is_pk'] ?? 0) == 1;
            $isRequired = ($column['is_required'] ?? 0) == 1;
            // 跳过主键字段（已在模板中固定 id bigint）
            if ($isPk) {
                continue;
            }
            // 跳过已自动处理的 created_at / updated_at
            if (in_array($colName, ['created_at', 'updated_at'], true)) {
                continue;
            }
            // 跳过软删除字段（单独处理）
            if ($hasDeleteColumn && $colName === $deleteColumnName) {
                continue;
            }

            $sqlType = self::TYPE_MAP[strtolower($colType)] ?? 'varchar(255)';
            $nullable = $isRequired ? 'NOT NULL' : 'DEFAULT NULL';
            $comment = !empty($colComment) ? "COMMENT '{$colComment}'" : '';

            $fieldLines[] = "    `{$colName}` {$sqlType} {$nullable} {$comment}";
        }

        // 软删除字段
        if ($hasDeleteColumn) {
            $softDeleteLine = "`{$deleteColumnName}` int(10) unsigned DEFAULT NULL COMMENT '删除时间'";
        } else {
            $softDeleteLine = '';
        }

        // 生成普通索引
        foreach ($columns as $column) {
            $colName = $column['column_name'] ?? '';
            $isSearch = ($column['is_search'] ?? 0) == 1;
            if ($isSearch && !in_array($colName, ['id', 'created_at', 'updated_at', $deleteColumnName], true)) {
                $indexLines[] = "    INDEX `idx_{$tableName}_{$colName}` (`{$colName}`) USING BTREE COMMENT '{$colName}索引'";
            }
        }

        $data = [
            'class_name'   => $className,
            'table_name'   => $tableName,
            'table_comment' => $tableComment,
            'created_at'   => date('Y-m-d H:i:s'),
            'fields'       => implode(",\n", $fieldLines),
            'indexes'      => !empty($indexLines) ? implode(",\n", $indexLines) : "INDEX `idx_{$tableName}_created_at` (`created_at`) USING BTREE",
            'soft_delete'  => $softDeleteLine,
        ];

        return $this->templateRenderer->render('server/migration/migration.stub', $data);
    }

    public function getFileExtension(): string
    {
        return 'sql';
    }
}
