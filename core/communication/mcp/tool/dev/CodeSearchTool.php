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
use Symfony\Component\Finder\Finder;

/**
 * code_search：代码定位检索工具（登录可见）
 *
 * 仅在白名单根目录（app/、core/）内递归检索 PHP 文件，
 * 返回 文件路径 + 行号 + 匹配行，不回传整文件内容，从源头防源码越界泄漏。
 */
final class CodeSearchTool
{
    /** 允许检索的根目录（相对 base_path） */
    private const ALLOWED_ROOTS = ['app', 'core'];

    /** 默认/最大返回条数 */
    private const DEFAULT_LIMIT = 50;
    private const MAX_LIMIT = 100;

    /** 单条匹配行文本截断长度，避免长行撑爆上下文 */
    private const MAX_LINE_LENGTH = 300;

    public function __construct(
        private readonly ?McpUser $user = null,
    ) {
    }

    #[McpTool(
        name: 'code_search',
        title: 'Code Search',
        description: '在 app/ 与 core/ 目录内按关键字检索 PHP 源码，返回文件相对路径、行号与匹配行片段（不返回整文件）。可指定子目录缩小范围，默认不区分大小写，最多返回 100 条匹配。',
        inputSchema: [
            'type' => 'object',
            'required' => ['keyword'],
            'properties' => [
                'keyword' => [
                    'type' => 'string',
                    'minLength' => 2,
                    'description' => '检索关键字（按子串匹配），至少 2 个字符',
                ],
                'path' => [
                    'type' => 'string',
                    'description' => '可选子目录。支持相对 base（app/adminapi/controller、core/communication/mcp）或相对根目录（adminapi/controller、communication/mcp），禁止越出 app/core',
                ],
                'case_sensitive' => [
                    'type' => 'boolean',
                    'description' => '是否区分大小写，默认 false',
                ],
                'limit' => [
                    'type' => 'integer',
                    'minimum' => 1,
                    'maximum' => 100,
                    'description' => '返回条数上限，默认 50，最大 100',
                ],
            ],
        ],
        permission: false,
    )]
    public function search(
        string $keyword,
        ?string $path = null,
        bool $case_sensitive = false,
        int $limit = self::DEFAULT_LIMIT,
    ): array {
        $keyword = trim($keyword);
        if (mb_strlen($keyword) < 2) {
            throw new \InvalidArgumentException('keyword 至少 2 个字符');
        }

        $limit = max(1, min($limit, self::MAX_LIMIT));
        $searchDirs = $this->resolveDirs($path === null ? '' : trim($path));

        $finder = (new Finder())
            ->files()
            ->name('*.php')
            ->in($searchDirs)
            ->sortByName()
            ->ignoreUnreadableDirs();

        $matches = [];
        $filesScanned = 0;

        foreach ($finder as $file) {
            $filesScanned++;

            $absolute = $file->getPathname();
            $handle = @fopen($absolute, 'r');
            if ($handle === false) {
                continue;
            }

            $lineNo = 0;
            while (($line = fgets($handle)) !== false) {
                $lineNo++;
                $matched = $case_sensitive
                    ? str_contains($line, $keyword)
                    : str_contains(strtolower($line), strtolower($keyword));
                if (!$matched) {
                    continue;
                }

                $matches[] = [
                    'file' => $this->relativePath($absolute),
                    'line' => $lineNo,
                    'text' => $this->truncate(trim($line)),
                ];

                if (count($matches) >= $limit) {
                    fclose($handle);
                    break 2;
                }
            }
            fclose($handle);
        }

        return [
            'keyword' => $keyword,
            'path' => $path,
            'case_sensitive' => $case_sensitive,
            'files_scanned' => $filesScanned,
            'returned' => count($matches),
            'truncated' => count($matches) >= $limit,
            'matches' => $matches,
        ];
    }

    /**
     * 将可选子目录解析为根目录内的绝对路径，任何越界尝试直接拒绝
     *
     * 支持两种写法：
     *  - 相对 base_path：app/adminapi/controller、core/communication/mcp
     *  - 相对某一根目录：adminapi/controller（app 下）、communication/mcp（core 下）
     *
     * @return string[]
     */
    private function resolveDirs(string $subPath): array
    {
        if ($subPath === '') {
            return $this->existingRoots();
        }

        $normalized = trim(str_replace('\\', '/', $subPath), '/');

        // 写法一：显式带根前缀（app/... 或 core/...），直接相对 base_path 解析
        foreach (self::ALLOWED_ROOTS as $root) {
            if ($normalized === $root || str_starts_with($normalized, $root . '/')) {
                $dir = $this->guardInside(base_path($normalized), base_path($root));
                return $dir === null ? [] : [$dir];
            }
        }

        // 写法二：相对各根目录解析，命中哪个算哪个
        $dirs = [];
        foreach (self::ALLOWED_ROOTS as $root) {
            $dir = $this->guardInside(
                base_path($root . '/' . $normalized),
                base_path($root),
            );
            if ($dir !== null) {
                $dirs[] = $dir;
            }
        }

        if ($dirs === []) {
            throw new \InvalidArgumentException(sprintf(
                '检索目录不存在或越出允许根目录（仅允许：%s）',
                implode(', ', self::ALLOWED_ROOTS),
            ));
        }

        return array_values(array_unique($dirs));
    }

    /**
     * realpath 规范化并确认 target 仍在 root 内，越界/不存在返回 null
     */
    private function guardInside(string $target, string $root): ?string
    {
        $realTarget = realpath($target);
        $realRoot = realpath($root);
        if ($realTarget === false || $realRoot === false || !is_dir($realTarget)) {
            return null;
        }
        if (!str_starts_with($realTarget . DIRECTORY_SEPARATOR, $realRoot . DIRECTORY_SEPARATOR)) {
            return null;
        }
        return $realTarget;
    }

    /**
     * @return string[]
     */
    private function existingRoots(): array
    {
        $dirs = [];
        foreach (self::ALLOWED_ROOTS as $root) {
            $rootDir = base_path($root);
            if (is_dir($rootDir)) {
                $dirs[] = $rootDir;
            }
        }
        return $dirs;
    }

    private function relativePath(string $absolute): string
    {
        $base = rtrim(str_replace('\\', '/', base_path()), '/');
        $normalized = str_replace('\\', '/', $absolute);
        return str_starts_with($normalized, $base . '/')
            ? substr($normalized, strlen($base) + 1)
            : $normalized;
    }

    private function truncate(string $text): string
    {
        return mb_strlen($text) > self::MAX_LINE_LENGTH
            ? mb_substr($text, 0, self::MAX_LINE_LENGTH) . ' …'
            : $text;
    }
}
