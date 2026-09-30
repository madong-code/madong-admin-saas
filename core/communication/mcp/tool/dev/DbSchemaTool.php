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

namespace core\communication\mcp\tool\dev;

use core\communication\mcp\attribute\McpTool;
use core\communication\mcp\security\McpUser;
use core\communication\mcp\support\McpTenant;

/**
 * db_schema：数据库表结构查询（information_schema，只读）
 *
 * 字段查询仅允许单表（不给 table 绝不返回 COLUMNS），两次查询均强制 LIMIT。
 * 表名白名单：默认仅放行 sys_ 前缀系统表，可经 config/mcp.php 的
 * tools.db_schema.allowed_prefixes 配置收窄/放宽；设为空数组则暴露全库。
 */
final class DbSchemaTool
{
    /**
     * 默认允许的逻辑表名前缀（去掉连接前缀后）
     * 设为 [] 表示不限制；可被 config('mcp.tools.db_schema.allowed_prefixes') 覆盖
     */
    private const DEFAULT_ALLOWED_PREFIXES = ['sys_'];

    public function __construct(
        private readonly ?McpUser $user = null,
    ) {
    }

    #[McpTool(
        name: 'db_schema',
        title: 'DB Schema',
        description: '查询 madong 数据库表结构：不给 table 返回表清单（表名/注释/行数估算），给 table 返回该表字段清单（名称/类型/可空/默认值/注释/键）。',
        inputSchema: [
            'type' => 'object',
            'properties' => [
                'table' => [
                    'type' => 'string',
                    'description' => '表名（物理名，含 md_ 前缀）。给出则返回该表字段清单',
                ],
                'keyword' => [
                    'type' => 'string',
                    'description' => '表名过滤关键词（仅表清单模式生效）',
                ],
                'limit' => [
                    'type' => 'integer',
                    'default' => 100,
                    'maximum' => 500,
                    'description' => '返回条数上限',
                ],
            ],
        ],
        permission: false,
    )]
    public function dbSchema(?string $table = null, string $keyword = '', int $limit = 100): array
    {
        if (!config('mcp.tools.db_schema', true)) {
            return ['enabled' => false, 'message' => 'db_schema 已在 config/mcp.php 的 tools 节点禁用'];
        }

        $limit   = min(500, max(1, $limit));
        $keyword = trim($keyword);
        $table   = trim((string) $table);

        if ($table !== '') {
            return $this->columns($table, $limit);
        }
        return $this->tables($keyword, $limit);
    }

    private function tables(string $keyword, int $limit): array
    {
        $prefix = $this->tablePrefix();
        $allowed = $this->allowedPrefixes();

        $rows = McpTenant::connection()->select(
            'SELECT TABLE_NAME AS name, TABLE_COMMENT AS comment, TABLE_ROWS AS `rows`'
            . ' FROM information_schema.TABLES'
            . ' WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME LIKE ?'
            . ' ORDER BY TABLE_NAME LIMIT ' . (int) $limit,
            ['%' . $keyword . '%'],
        );

        $tables = [];
        foreach ($rows as $row) {
            $physical = (string) $row->name;
            $logical = $this->stripPrefix($physical, $prefix);
            if (!$this->isAllowed($logical, $allowed)) {
                continue;
            }
            $tables[] = [
                'name'     => $logical,
                'physical' => $physical,
                'comment'  => $row->comment,
                'rows'     => $row->rows,
            ];
        }

        return [
            'total'  => count($tables),
            'allowed_prefixes' => $allowed,
            'tables' => $tables,
        ];
    }

    private function columns(string $table, int $limit): array
    {
        $prefix = (string) config(
            'database.connections.' . (string) config('database.default', 'mysql') . '.prefix',
            '',
        );
        // 裸逻辑名（sys_menu）自动补连接前缀重试；物理名（ma_sys_menu）直接查
        $candidates = [$table];
        if ($prefix !== '' && !str_starts_with($table, $prefix)) {
            $candidates[] = $prefix . $table;
        }

        $rows = [];
        $resolved = $table;
        $allowed = $this->allowedPrefixes();
        foreach ($candidates as $candidate) {
            $logical = $this->stripPrefix($candidate, $prefix);
            if (!$this->isAllowed($logical, $allowed)) {
                // 该候选表不在白名单内，跳过（不在循环内 early return 以兼容多候选）
                continue;
            }
            $exists = McpTenant::connection()->select(
                'SELECT COUNT(*) AS total FROM information_schema.TABLES'
                . ' WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
                [$candidate],
            );
            if ((int) ($exists[0]->total ?? 0) > 0) {
                $resolved = $candidate;
                $rows = McpTenant::connection()->select(
                    'SELECT COLUMN_NAME AS name, COLUMN_TYPE AS type, IS_NULLABLE AS nullable,'
                    . ' COLUMN_DEFAULT AS def, COLUMN_COMMENT AS comment, COLUMN_KEY AS `key`'
                    . ' FROM information_schema.COLUMNS'
                    . ' WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'
                    . ' ORDER BY ORDINAL_POSITION LIMIT ' . (int) $limit,
                    [$candidate],
                );
                break;
            }
        }

        // 命中候选均被白名单拦截，统一返回拒绝信息
        $resolvedLogical = $this->stripPrefix($resolved, $prefix);
        if ($rows === [] && !$this->isAllowed($resolvedLogical, $allowed)) {
            return [
                'table'  => $resolvedLogical,
                'total'  => 0,
                'columns' => [],
                'message' => sprintf(
                    '表 "%s" 不在可访问白名单内，当前允许前缀：%s（设 config/mcp.php tools.db_schema.allowed_prefixes = [] 可放开全库）',
                    $resolvedLogical,
                    $allowed === [] ? '无限制' : implode(', ', $allowed),
                ),
            ];
        }

        return [
            'table'   => $resolved,
            'logical' => $resolvedLogical,
            'total'   => count($rows),
            'columns' => array_map(static fn (object $row): array => (array) $row, $rows),
            'message' => $rows === [] ? '表不存在' : '',
        ];
    }

    /**
     * 允许的逻辑表名前缀列表（空数组 = 不限制）
     *
     * @return string[]
     */
    private function allowedPrefixes(): array
    {
        $cfg = config('mcp.tools.db_schema.allowed_prefixes', self::DEFAULT_ALLOWED_PREFIXES);
        return is_array($cfg) ? $cfg : self::DEFAULT_ALLOWED_PREFIXES;
    }

    /**
     * 逻辑表名是否在白名单内；白名单为空时恒放行
     *
     * @param string[] $allowed
     */
    private function isAllowed(string $logical, array $allowed): bool
    {
        if ($allowed === []) {
            return true;
        }
        foreach ($allowed as $prefix) {
            if (str_starts_with($logical, $prefix)) {
                return true;
            }
        }
        return false;
    }

    private function tablePrefix(): string
    {
        return (string) config(
            'database.connections.' . (string) config('database.default', 'mysql') . '.prefix',
            '',
        );
    }

    private function stripPrefix(string $table, string $prefix): string
    {
        if ($prefix !== '' && str_starts_with($table, $prefix)) {
            return substr($table, strlen($prefix));
        }
        return $table;
    }
}
