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
 * Official Website: https://madong.tech
 */

namespace core\io\excel;

/**
 * Excel 导出文件安全路径守卫
 *
 * 导出文件只允许位于 runtime/exports/ 下且扩展名为 .xlsx。
 * 控制器与队列消费者必须共用本类：任一入口都不得直接把请求参数拼到 runtime_path()。
 *
 * @author Mr.April
 * @since  1.0
 */
final class ExportPathGuard
{
    /** 导出目录（相对 runtime_path()） */
    public const EXPORT_DIR = 'exports';

    /** 允许的文件扩展名 */
    public const ALLOWED_EXTENSION = 'xlsx';

    /**
     * 校验并归一化导出文件的相对路径
     *
     * 兼容业务侧生成的 '/exports/xxx.xlsx' 前导斜杠形式，返回值统一为 'exports/xxx.xlsx'。
     * 仅做格式校验，不访问文件系统，可用于队列消息字段白名单。
     *
     * @param string $relativePath 相对路径
     *
     * @return string 归一化后的相对路径
     * @throws \InvalidArgumentException 路径非法时抛出
     */
    public static function normalize(string $relativePath): string
    {
        $path = trim($relativePath);
        if ($path === '' || strlen($path) > 255 || preg_match('/[\x00-\x1F\x7F]/', $path) === 1) {
            throw new \InvalidArgumentException('非法的导出文件路径');
        }

        // Windows 分隔符统一；拒绝 URL scheme 与盘符绝对路径
        $normalized = str_replace('\\', '/', $path);
        if (str_contains($normalized, '://') || preg_match('/^[A-Za-z]:/', $normalized) === 1) {
            throw new \InvalidArgumentException('非法的导出文件路径');
        }

        // '/exports/xxx.xlsx' 中的前导斜杠表示应用内根目录，不是文件系统绝对路径
        $segments = explode('/', ltrim($normalized, '/'));
        if (count($segments) < 2 || $segments[0] !== self::EXPORT_DIR) {
            throw new \InvalidArgumentException('非法的导出文件路径');
        }

        foreach ($segments as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..' || str_contains($segment, '..')) {
                throw new \InvalidArgumentException('非法的导出文件路径');
            }
        }

        $filename = (string)end($segments);
        $extension = strtolower((string)pathinfo($filename, PATHINFO_EXTENSION));
        if ($extension !== self::ALLOWED_EXTENSION || strlen($filename) > 200) {
            throw new \InvalidArgumentException('非法的导出文件路径');
        }

        return implode('/', $segments);
    }

    /**
     * 解析为位于导出目录内的真实绝对路径
     *
     * realpath() 会解析符号链接，随后按「带目录分隔符的根前缀」比较，
     * 防止 ../ 穿越、符号链接逃逸以及 /exports-evil 之类的前缀绕过。
     *
     * @param string      $relativePath 相对路径
     * @param string|null $baseDir      自定义 runtime 根目录（测试环境使用）
     *
     * @return string 真实绝对路径
     * @throws \InvalidArgumentException 路径越界、文件不存在或不是普通文件时抛出
     */
    public static function resolve(string $relativePath, ?string $baseDir = null): string
    {
        $relative = self::normalize($relativePath);
        $root     = self::exportRoot($baseDir);
        $realRoot = realpath($root);

        if ($realRoot === false || !is_dir($realRoot)) {
            throw new \InvalidArgumentException('导出目录不可用');
        }

        $inner    = substr($relative, strlen(self::EXPORT_DIR) + 1);
        $candidate = $realRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $inner);
        $real      = realpath($candidate);

        if ($real === false || !is_file($real)) {
            throw new \InvalidArgumentException('导出文件不存在');
        }

        $rootPrefix = rtrim(str_replace('\\', '/', $realRoot), '/') . '/';
        $realPath   = str_replace('\\', '/', $real);
        // Windows 下盘符大小写可能不一致，使用大小写不敏感比较
        if (strncasecmp($realPath, $rootPrefix, strlen($rootPrefix)) !== 0) {
            throw new \InvalidArgumentException('导出文件路径越界');
        }

        return $real;
    }

    /**
     * 过滤下载响应中的展示文件名
     *
     * 去除路径符、控制字符、CRLF 与目录跳转片段，避免响应头注入与目录提示。
     */
    public static function sanitizeDownloadName(string $name): string
    {
        $name = preg_replace('/[\x00-\x1F\x7F]/', '', $name) ?? '';
        $name = str_replace(['/', '\\'], '', $name);
        $name = preg_replace('/\.{2,}/', '.', $name) ?? $name;
        $name = trim($name);

        if ($name === '' || $name === '.') {
            return 'export.' . self::ALLOWED_EXTENSION;
        }

        return self::truncate($name, 180);
    }

    /**
     * 过滤导出时使用的磁盘文件名
     *
     * 同时禁止会污染下载 URL 查询串的字符（& = 等）。
     */
    public static function sanitizeFilename(string $name): string
    {
        $name = preg_replace('/[\x00-\x1F\x7F]/', '', $name) ?? '';
        $name = str_replace(
            ['/', '\\', '?', '*', '[', ']', ':', '"', "'", '&', '=', '#', '%'],
            '',
            $name
        );
        $name = preg_replace('/\.{2,}/', '.', $name) ?? $name;
        $name = trim($name, " .\t");

        if ($name === '') {
            $name = 'export_' . date('YmdHis');
        }

        return self::truncate($name, 120);
    }

    /**
     * 导出目录绝对路径
     */
    public static function exportRoot(?string $baseDir = null): string
    {
        if ($baseDir === null) {
            // 未启动 Webman 时（如 CLI 单测）回退到项目 runtime 目录
            $baseDir = function_exists('runtime_path')
                ? runtime_path()
                : dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'runtime';
        }

        return rtrim(str_replace('/', DIRECTORY_SEPARATOR, $baseDir), DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR . self::EXPORT_DIR;
    }

    /**
     * 按字符截断，避免多字节文件名被截出半个字符
     */
    private static function truncate(string $value, int $length): string
    {
        return function_exists('mb_substr')
            ? mb_substr($value, 0, $length)
            : substr($value, 0, $length);
    }
}
