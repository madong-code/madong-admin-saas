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

namespace core\communication\mcp\tool\ci;

use core\communication\mcp\attribute\McpTool;
use core\communication\mcp\security\McpUser;
use core\communication\mcp\support\McpTenant;

/**
 * db_query：只读 SQL 查询（权限码 mcp:db:query）
 *
 * 安全约束：
 *  - 仅允许 SELECT 语句（去掉注释与空白后判断）
 *  - 禁止多语句（; 分割）
 *  - 强制 LIMIT 100（无 LIMIT 时自动追加）
 *  - 表白名单：默认仅 sys_ 前缀，可经 config('mcp.tools.db_schema.allowed_prefixes') 配置
 */
final class DbQueryTool
{
    /** 自动追加的最大行数 */
    private const MAX_ROWS = 100;

    /** 默认允许的逻辑表名前缀 */
    private const DEFAULT_ALLOWED_PREFIXES = ['sys_'];

    public function __construct(
        private readonly ?McpUser $user = null,
    ) {
    }

    #[McpTool(
        name: 'db_query',
        title: 'DB Query',
        description: '⚠️ 写操作风险：执行只读 SQL（SELECT）。禁止 INSERT/UPDATE/DELETE/DROP/ALTER 等写语句与多语句；无 LIMIT 时自动追加 LIMIT 100；表名受白名单约束（默认 sys_）。需要权限码 mcp:db:query。',
        inputSchema: [
            'type'       => 'object',
            'properties' => [
                'sql' => [
                    'type'        => 'string',
                    'description' => '只读 SELECT SQL，表名用逻辑名（如 sys_admin），无需加连接前缀',
                ],
            ],
            'required' => ['sql'],
        ],
        permission: 'mcp:db:query',
    )]
    public function query(string $sql): array
    {
        $sql = trim($sql);
        if ($sql === '') {
            return ['total' => 0, 'rows' => [], 'message' => 'sql 不能为空'];
        }

        // 去掉 /* ... */ 和 -- 行注释、空白，用于安全判断
        $stripped = $this->stripComments($sql);
        $normalized = strtolower(trim(preg_replace('/\s+/', ' ', $stripped)));

        // 必须 SELECT 开头
        if (!str_starts_with($normalized, 'select')) {
            return ['total' => 0, 'rows' => [], 'message' => '仅允许 SELECT 语句'];
        }

        // 禁止多语句：剥离字符串后不允许出现 ;
        if (preg_match('/;(?![\'"])/', $this->stripStringLiterals($normalized))) {
            return ['total' => 0, 'rows' => [], 'message' => '禁止多语句执行（不允许出现分号）'];
        }

        // 禁止危险关键字
        if (preg_match('/\b(insert|update|delete|drop|alter|truncate|create|replace|grant|revoke|lock|unlock|set|load|into\s+outfile|into\s+dumpfile)\b/', $normalized)) {
            return ['total' => 0, 'rows' => [], 'message' => 'SQL 包含禁止的写操作关键字'];
        }

        // 表白名单校验
        $allowed = $this->allowedPrefixes();
        $tables = $this->extractTables($normalized);
        $prefix = $this->tablePrefix();
        $denied = [];
        foreach ($tables as $table) {
            $logical = $this->stripPrefix($table, $prefix);
            if (!$this->isAllowed($logical, $allowed)) {
                $denied[] = $logical;
            }
        }
        if ($denied !== []) {
            return [
                'total'   => 0,
                'rows'    => [],
                'message' => '以下表不在白名单内：' . implode(', ', $denied)
                    . '；当前允许前缀：' . ($allowed === [] ? '无限制' : implode(', ', $allowed)),
            ];
        }

        // 字段隔离且已绑定租户：原始 SQL 无法安全重写为带租户条件，禁止直接查租户表，
        // 避免跨租户读取（请改用 admin_user_list / menu_catalog / dict_catalog 等租户感知工具）
        $tenantScoped = [];
        foreach ($tables as $table) {
            $logical = $this->stripPrefix($table, $prefix);
            if (McpTenant::isTenantScopedTable($logical)) {
                $tenantScoped[] = $logical;
            }
        }
        if ($tenantScoped !== []) {
            return [
                'total'   => 0,
                'rows'    => [],
                'message' => '当前为租户身份（字段隔离），以下表受租户隔离，原始 SQL 无法自动加租户条件，已拒绝：'
                    . implode(', ', array_unique($tenantScoped))
                    . '；请改用租户感知工具（admin_user_list / admin_account_query / menu_catalog / dict_catalog）',
            ];
        }

        // 无 LIMIT 则强制追加
        if (!preg_match('/\blimit\s+\d+/', $normalized)) {
            $sql .= ' LIMIT ' . self::MAX_ROWS;
        }

        // 逻辑表名 → 物理表名（自动加连接前缀，已带前缀的不重复加）
        if ($prefix !== '') {
            $sql = $this->addTablePrefix($sql, $prefix);
        }

        try {
            $rows = McpTenant::connection()->select($sql);
        } catch (\Throwable $e) {
            return ['total' => 0, 'rows' => [], 'message' => 'SQL 执行失败：' . $e->getMessage()];
        }

        return [
            'total' => count($rows),
            'rows'  => array_map(static fn ($r) => (array) $r, $rows),
        ];
    }

    /**
     * 提取 SQL 中出现的表名（FROM/JOIN 后），去重
     *
     * @return string[]
     */
    private function extractTables(string $normalized): array
    {
        $tables = [];
        // 匹配 FROM <table> 和 JOIN <table>
        if (preg_match_all('/\b(?:from|join)\s+([`"\[]?[\w]+[`"\]]?)/', $normalized, $m)) {
            foreach ($m[1] as $t) {
                $t = trim($t, '`"[]');
                if ($t !== '' && !in_array($t, $tables, true)) {
                    $tables[] = $t;
                }
            }
        }
        return $tables;
    }

    /**
     * 移除 /* *\/ 和 -- 行注释（不完美，但足以做安全判断）
     */
    private function stripComments(string $sql): string
    {
        // /* ... */
        $sql = preg_replace('!/\*.*?\*/!s', ' ', $sql) ?? $sql;
        // -- 到行尾
        $sql = preg_replace('/--[^\r\n]*/', ' ', $sql) ?? $sql;
        // # 到行尾
        return preg_replace('/#[^\r\n]*/', ' ', $sql) ?? $sql;
    }

    /**
     * 移除字符串字面量，用于检测分号
     */
    private function stripStringLiterals(string $sql): string
    {
        return preg_replace("/'[^']*'/", '', preg_replace('/"[^"]*"/', '', $sql) ?? '') ?? $sql;
    }

    /**
     * 将 SQL 中 FROM/JOIN 后的逻辑表名替换为带前缀的物理表名
     * 已带前缀的表名不重复添加
     */
    private function addTablePrefix(string $sql, string $prefix): string
    {
        return preg_replace_callback(
            '/\b(from|join)\s+([`"\[]?)([\w]+)([`"\]]?)/i',
            function ($m) use ($prefix) {
                $table = $m[3];
                if (!str_starts_with($table, $prefix)) {
                    $table = $prefix . $table;
                }
                return $m[1] . ' ' . $m[2] . $table . $m[4];
            },
            $sql,
        ) ?? $sql;
    }

    /**
     * @return string[]
     */
    private function allowedPrefixes(): array
    {
        $cfg = config('mcp.tools.db_schema.allowed_prefixes', self::DEFAULT_ALLOWED_PREFIXES);
        return is_array($cfg) ? $cfg : self::DEFAULT_ALLOWED_PREFIXES;
    }

    /**
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
