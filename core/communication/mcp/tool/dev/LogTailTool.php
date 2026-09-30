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

/**
 * log_tail：运行日志尾部查询（fstat + 倒序块读取，不整读大文件）
 *
 * 安全：仅允许 runtime/logs 目录下实际存在的 .log 文件（basename 白名单 + realpath 校验）。
 */
final class LogTailTool
{
    private const CHUNK_SIZE  = 8192;
    private const MAX_SCAN    = 2097152; // 2MB 扫描上限（防巨型单行日志）
    private const LOG_DIR     = 'runtime' . DIRECTORY_SEPARATOR . 'logs';

    public function __construct(
        private readonly ?McpUser $user = null,
    ) {
    }

    #[McpTool(
        name: 'log_tail',
        title: 'Log Tail',
        description: '读取 madong 运行日志（runtime/logs/*.log）尾部若干行，支持行内关键词过滤，用于排障定位最新错误。file 为空时自动选取最新的 webman-*.log。',
        inputSchema: [
            'type' => 'object',
            'properties' => [
                'file' => [
                    'type' => 'string',
                    'description' => '日志文件名（仅限 runtime/logs 下的实际文件，如 webman-2026-09-10.log），空=最新 webman 日志',
                ],
                'lines' => [
                    'type' => 'integer',
                    'default' => 100,
                    'maximum' => 500,
                    'description' => '返回行数（取尾部）',
                ],
                'keyword' => [
                    'type' => 'string',
                    'description' => '行内关键词过滤（空=不过滤）',
                ],
            ],
        ],
        permission: false,
    )]
    public function logTail(string $file = '', int $lines = 100, string $keyword = ''): array
    {
        if (!config('mcp.tools.log_tail', true)) {
            return ['enabled' => false, 'message' => 'log_tail 已在 config/mcp.php 的 tools 节点禁用'];
        }

        $lines   = min(500, max(1, $lines));
        $keyword = trim($keyword);
        $dir     = base_path(self::LOG_DIR);

        $path = $this->resolveFile($dir, trim($file));
        if ($path === null) {
            return [
                'total'           => 0,
                'lines'           => [],
                'available_files' => array_map(
                    static fn (string $p): string => basename($p),
                    array_merge(glob($dir . DIRECTORY_SEPARATOR . '*.log') ?: []),
                ),
                'message'         => '日志文件不存在或不在白名单内',
            ];
        }

        [$content, $reachedStart] = $this->readTail($path, $keyword !== '' ? $lines * 3 : $lines);
        $all = explode("\n", $content);
        // 未扫到文件头时，首块可能截断半行，丢弃首个元素
        if (!$reachedStart) {
            array_shift($all);
        }
        if ($keyword !== '') {
            $all = array_values(array_filter(
                $all,
                static fn (string $line): bool => stripos($line, $keyword) !== false,
            ));
        }

        $selected = array_slice($all, -$lines);
        $selected = array_map(static fn (string $line): string => rtrim($line, "\r"), $selected);

        return [
            'file'  => basename($path),
            'size'  => filesize($path),
            'total' => count($selected),
            'lines' => $selected,
        ];
    }

    /**
     * 解析目标文件：白名单 = 目录下实际存在的 .log 文件
     */
    private function resolveFile(string $dir, string $file): ?string
    {
        if (!is_dir($dir)) {
            return null;
        }

        if ($file === '') {
            $latest = null;
            $latestMtime = -1;
            foreach (glob($dir . DIRECTORY_SEPARATOR . 'webman-*.log') ?: [] as $candidate) {
                $mtime = @filemtime($candidate) ?: 0;
                if ($mtime >= $latestMtime) {
                    $latestMtime = $mtime;
                    $latest = $candidate;
                }
            }
            return $latest;
        }

        // 拒绝路径分隔符与穿越片段
        if (str_contains($file, '/') || str_contains($file, '\\') || str_contains($file, '..')) {
            return null;
        }
        if (!str_ends_with($file, '.log')) {
            return null;
        }

        $path = $dir . DIRECTORY_SEPARATOR . basename($file);
        if (!is_file($path)) {
            return null;
        }
        // realpath 校验必须落在 logs 目录内
        $real = realpath($path);
        $realDir = (string) realpath($dir);
        if ($real === false || !str_starts_with($real, $realDir . DIRECTORY_SEPARATOR)) {
            return null;
        }
        return $real;
    }

    /**
     * 倒序块读取尾部内容：收集 >= $need 行（不含文件头截断误差），最多扫 MAX_SCAN 字节
     *
     * @return array{0: string, 1: bool} [缓冲内容, 是否已读到文件头]
     */
    private function readTail(string $path, int $need): array
    {
        $fp = @fopen($path, 'rb');
        if ($fp === false) {
            return ['', true];
        }

        try {
            fseek($fp, 0, SEEK_END);
            $scanned = 0;
            $buffer  = '';

            while ($scanned < self::MAX_SCAN && ftell($fp) > 0) {
                $read = min(self::CHUNK_SIZE, ftell($fp), self::MAX_SCAN - $scanned);
                fseek($fp, -$read, SEEK_CUR);
                $buffer = (string) fread($fp, $read) . $buffer;
                fseek($fp, -$read, SEEK_CUR);
                $scanned += $read;
                if (substr_count($buffer, "\n") > $need) {
                    break;
                }
            }
            return [$buffer, ftell($fp) === 0];
        } finally {
            fclose($fp);
        }
    }
}
