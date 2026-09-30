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
 * Here is your custom functions.
 */

use core\foundation\exception\handler\CommonException;
use Webman\Route;

if (!function_exists('full_url')) {
    /**
     * 获取资源完整url地址；若安装了云存储或 config/madong/upload/app.php 配置了cdn_url，则自动使用对应的cdn_url
     *
     * @param string      $relativeUrl 资源相对地址 不传入则获取域名
     * @param bool|string $domain      是否携带域名 或者直接传入域名
     * @param string      $default     默认值
     *
     * @return string
     */
    function full_url(string $relativeUrl = '', bool|string $domain = true, string $default = ''): string
    {
        // 从配置获取 CDN URL
        $cdnUrl = config('madong.upload.app.cdn_url', '');

        // 如果 CDN URL 为空，则使用默认的主机名
        if (empty($cdnUrl)) {
            $cdnUrl = '//' . request()->host();
        }

        // 处理域名
        if ($domain === true) {
            $domain = $cdnUrl;
        } elseif ($domain === false) {
            $domain = '';
        }

        // 如果没有相对 URL，使用默认值
        $relativeUrl = $relativeUrl ?: $default;
        if (!$relativeUrl) {
            return $domain;
        }

        // 检查是否为绝对 URL 或数据 URL
        $isAbsoluteUrl = preg_match('/^http(s)?:\/\//', $relativeUrl) || preg_match("/^((?:[a-z]+:)?\/\/|data:image\/)(.*)/i", $relativeUrl);
        if ($isAbsoluteUrl || $domain === false) {
            return $relativeUrl;
        }

        // 拼接最终 URL
        $url = $domain . $relativeUrl;

        // 添加 CDN URL 参数
        $cdnUrlParams = config('madong.upload.app.cdn_url_params');
        if ($domain === $cdnUrl && $cdnUrlParams) {
            $separator = str_contains($url, '?') ? '&' : '?';
            $url       .= $separator . $cdnUrlParams;
        }
        return $url;
    }

}

if (!function_exists('array_except')) {
    function array_except(array $array, array $keys): array
    {
        return array_diff_key($array, array_flip($keys));
    }
}

/**
 * 判断 文件/目录 是否可写
 *
 * @param string $file 文件/目录
 *
 * @return boolean
 */
if (!function_exists('is_write')) {
    function is_write(string $file): bool
    {
        if (is_dir($file)) {
            $dir = $file;
            if ($fp = @fopen("$dir/test.txt", 'wb')) {
                @fclose($fp);
                @unlink("$dir/test.txt");
                $writeable = true;
            } else {
                $writeable = false;
            }
        } else {
            if ($fp = @fopen($file, 'ab+')) {
                @fclose($fp);
                $writeable = true;
            } else {
                $writeable = false;
            }
        }
        return $writeable;
    }
}

/**
 * 获取路由地址
 *
 * @param string $nameOrPath 路由 name 或 path
 * @param array  $params
 *
 * @return string
 */
function route_url(string $nameOrPath, array $params = []): string
{
    $route = Route::getByName($nameOrPath);
    if (!$route) {
        $route = new Route\Route([], $nameOrPath, function () {
        });
    }

    return \request()->pathPrefix() . $route->url($params);
}

if (!function_exists('create_directory')) {

    /**
     * 创建文件夹
     *
     * @param string $path      文件夹路径
     * @param int    $mode      访问权限
     * @param bool   $recursive 是否递归创建
     *
     * @return bool
     * @throws \Exception
     */
    function create_directory(string $path = '', int $mode = 0777, bool $recursive = true): bool
    {
        clearstatcache();

        // 如果路径为空，抛出异常
        if (empty($path)) {
            throw new CommonException("目录路径不能为空");
        }

        if (!is_dir($path)) {
            if (mkdir($path, $mode, $recursive)) {
                return chmod($path, $mode);
            } else {
                throw new CommonException("目录{$path}创建失败请检查是否有足够的权限");
            }
        }
        return true;
    }
}

/**
 * 删除文件
 *
 * @param string $dst
 * @param array  $dirs
 *
 * @return bool
 */
if (!function_exists('remove_directory')) {
    /**
     * 删除文件
     *
     * @param string $dst
     * @param array  $dirs
     *
     * @return bool
     */
    function remove_directory(string $dst = '', array $dirs = []): bool
    {
        if (empty($dirs) || empty($dst)) {
            return false;
        }
        foreach ($dirs as $v) {
            @unlink($dst . $v);
        }
        return true;
    }
}

if (!function_exists('remove_target_directory')) {
    /**
     * 递归删除目录及其内容
     *
     * @param string $path      要删除的目录或文件路径
     * @param bool   $removeDir 是否删除目录本身
     *
     * @return bool 成功返回 true，失败返回 false
     */
    function remove_target_directory(string $path, bool $removeDir = true): bool
    {
        // 路径不存在，直接返回 false
        if (!file_exists($path)) {
            return false;
        }

        // 如果是文件，直接删除
        if (is_file($path) || is_link($path)) {
            return unlink($path);
        }

        // 如果是目录，递归删除其内容
        $items = scandir($path);
        if ($items === false) {
            // 无法打开目录
            return false;
        }

        foreach ($items as $item) {
            // 跳过当前目录和上级目录
            if ($item === '.' || $item === '..') {
                continue;
            }

            // 构建完整路径
            $itemPath = $path . DIRECTORY_SEPARATOR . $item;

            // 递归删除子项
            if (!remove_target_directory($itemPath, true)) {
                // 如果子项删除失败，返回 false
                return false;
            }
        }

        // 如果需要删除目录本身
        if ($removeDir) {
            return rmdir($path);
        }

        return true;
    }
}

/**
 * 文件夹文件拷贝
 *
 * @param string $src           来源文件夹
 * @param string $dst           目的地文件夹
 * @param array  $files         文件夹集合
 * @param array  $exclude_dirs  排除无需拷贝的文件夹
 * @param array  $exclude_files 排除无需拷贝的文件
 *
 * @return bool
 * @throws \Exception
 */
function copy_directory(string $src = '', string $dst = '', array &$files = [], array $exclude_dirs = [], array $exclude_files = []): bool
{
    if (empty($src) || empty($dst)) {
        return false;
    }
    if (!file_exists($src)) {
        return false;
    }
    $dir = opendir($src);
    create_directory($dst);
    while (false !== ($file = readdir($dir))) {
        if (($file != '.') && ($file != '..')) {
            if (is_dir($src . '/' . $file)) {
                // 排除目录
                if (count($exclude_dirs) && in_array($file, $exclude_dirs)) continue;
                copy_directory($src . '/' . $file, $dst . '/' . $file, $files, $exclude_dirs, $exclude_files);
            } else {
                // 排除文件
                if (count($exclude_files) && in_array($file, $exclude_files)) continue;
                $copyResult = copy($src . '/' . $file, $dst . '/' . $file);
                $files[]    = $dst . '/' . $file;
                if (!$copyResult) {
                    closedir($dir);
                    throw new CommonException("文件{$file}拷贝失败请检查是否有足够的权限");
                }
            }
        }
    }
    closedir($dir);
    return true;
}

if (!function_exists('get_subdirectories')) {
    /**
     * 获取目录下的子目录结构
     *
     * @param string $directory_path 要扫描的目录路径
     *
     * @return array 子目录名称列表
     */
    function get_subdirectories(string $directory_path): array
    {
        // 验证目录有效性
        if (!is_dir($directory_path)) {
            trigger_error("Invalid directory path: {$directory_path}", E_USER_WARNING);
            return [];
        }

        $directory_handle = @opendir($directory_path);
        if ($directory_handle === false) {
            trigger_error("Failed to open directory: {$directory_path}", E_USER_WARNING);
            return [];
        }

        $subdirectories = [];

        while (($entry_name = readdir($directory_handle)) !== false) {
            // 跳过特殊目录
            if ($entry_name === '.' || $entry_name === '..') {
                continue;
            }

            $full_path = $directory_path . DIRECTORY_SEPARATOR . $entry_name;

            // 仅收集目录类型
            if (is_dir($full_path)) {
                $subdirectories[] = $entry_name;
            }
        }

        closedir($directory_handle);
        return $subdirectories;
    }
}

/**
 * 分割SQL语句并清理注释
 *
 * @param string $sqlContent              SQL内容
 * @param bool   $returnAsSingleString    是否返回单条SQL字符串（默认返回数组）
 * @param array  $tablePrefixReplacements 表前缀替换规则 [原前缀 => 新前缀]
 *
 * @return array|string 处理后的SQL语句数组或单条语句
 */
if (!function_exists('split_sql_statements')) {
    function split_sql_statements(
        string $sqlContent = '',
        bool   $returnAsSingleString = false,
        array  $tablePrefixReplacements = []
    ): array|string
    {
        // 存储清理后的SQL行
        $cleanedLines = [];
        // 获取表前缀替换规则（只取第一组替换规则）
        $originalPrefix = '';
        $newPrefix      = '';
        if (!empty($tablePrefixReplacements)) {
            $originalPrefix = key($tablePrefixReplacements);
            $newPrefix      = reset($tablePrefixReplacements);
        }

        // 处理空内容情况
        if ($sqlContent === '') {
            return $returnAsSingleString ? '' : [];
        }

        // 标准化换行符并分割为行数组
        $normalizedContent = str_replace(["\r\n", "\r"], "\n", $sqlContent);
        $lines             = explode("\n", trim($normalizedContent));

        // 处理多行注释状态
        $inMultilineComment = false;

        foreach ($lines as $line) {
            $trimmedLine = trim($line);

            // 跳过空行
            if ($trimmedLine === '') {
                continue;
            }

            // 处理多行注释开始
            if (str_starts_with($trimmedLine, '/*')) {
                $inMultilineComment = true;

                // 检查同一行是否包含注释结束
                if (str_contains($trimmedLine, '*/')) {
                    $inMultilineComment = false;
                }
                continue;
            }

            // 处理多行注释结束
            if (str_ends_with($trimmedLine, '*/')) {
                $inMultilineComment = false;
                continue;
            }

            // 跳过多行注释内容
            if ($inMultilineComment) {
                continue;
            }

            // 跳过单行注释 (# 或 -- 开头)
            if (preg_match('/^(#|--)/', $trimmedLine)) {
                continue;
            }

            // 跳过 /* */ 单行注释
            if (preg_match('/^\/\*.*?\*\/$/', $trimmedLine)) {
                continue;
            }

            // 应用表前缀替换
            if ($originalPrefix !== '' && $newPrefix !== '') {
                $line = str_replace(
                    '`' . $originalPrefix,
                    '`' . $newPrefix,
                    $line
                );
            }

            // 保存清理后的行
            $cleanedLines[] = $line;
        }

        // 返回单条SQL字符串
        if ($returnAsSingleString) {
            return implode(' ', $cleanedLines);
        }

        // 组合为完整SQL并分割语句
        $combinedSql = implode("\n", $cleanedLines);
        $statements  = explode(";\n", $combinedSql);

        // 移除空语句
        return array_filter($statements, function ($stmt) {
            return trim($stmt) !== '';
        });
    }
}

if (!function_exists('build_detailed_file_map')) {
    /**
     * 递归构建目录文件详细映射（包含目录大小）
     *
     * @param string $directory_path 目录路径
     * @param array  $file_map       累计文件映射（递归调用时传递）
     *
     * @return array 详细的文件映射数组（路径 => 详细信息）
     */
    function build_detailed_file_map(string $directory_path, array $file_map = []): array
    {
        if (!is_dir($directory_path)) {
            return $file_map;
        }

        $items = scandir($directory_path);
        foreach ($items as $item_name) {
            if ($item_name === '.' || $item_name === '..') continue;

            $item_path = $directory_path . DIRECTORY_SEPARATOR . $item_name;
            $item_type = is_dir($item_path) ? 'directory' : 'file';

            $file_map[$item_path] = [
                'name' => $item_name,
                'type' => $item_type,
                'size' => is_file($item_path) ? filesize($item_path) : 0,
            ];

            if ($item_type === 'directory') {
                $file_map = build_detailed_file_map($item_path, $file_map);
            }
        }

        return $file_map;
    }
}

if (!function_exists('project_path')) {
    /**
     * 获取项目根目录（项目目录）
     * User  GQL
     * Date  2026/1/4 15:21
     *
     * @return string
     */
    function project_path(): string
    {
        return dirname(base_path()) . DIRECTORY_SEPARATOR;
    }
}

/**
 * 递归收集目录下所有文件路径
 *
 * @param string $currentPath 当前处理的路径
 * @param array &$resultArray 存储结果的数组
 * @param string $stripPrefix 可选的路径前缀移除字符串
 *
 * @return void
 */
if (!function_exists('collect_files_recursive')) {
    function collect_files_recursive(string $currentPath, array &$resultArray, string $stripPrefix = ''): void
    {
        if (is_dir($currentPath)) {
            $normalizedDir = rtrim($currentPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
            $dirHandle     = opendir($normalizedDir);
            while (($entry = readdir($dirHandle)) !== false) {
                if ($entry === '.' || $entry === '..') continue;
                // 递归处理子路径
                collect_files_recursive(
                    $normalizedDir . $entry,
                    $resultArray,
                    $stripPrefix
                );
            }
            closedir($dirHandle);
        } elseif (is_file($currentPath)) {
            $processedPath = $currentPath;
            if ($stripPrefix !== '') {
                $processedPath = str_replace($stripPrefix, '', $processedPath);
            }
            $resultArray[] = $processedPath;
        }
    }
}

if (!function_exists('check_directory_permissions')) {
    /**
     * 检查目录及其子目录的读写权限
     *
     * @param string $directory_path       要检查的目录路径
     * @param array  $result_data          结果数据 [unreadable => [], not_writable => []]
     * @param array  $excluded_directories 排除检查的目录列表
     *
     * @return array 包含不可读和不可写路径的结果集
     * @throws RuntimeException 当指定路径不是有效目录时
     */
    function check_directory_permissions(
        string $directory_path,
        array  $result_data = [],
        array  $excluded_directories = []
    ): array
    {
        // 验证目录有效性
        if (!is_dir($directory_path)) {
            throw new RuntimeException(
                sprintf('指定的路径 "%s" 不是一个有效的目录', $directory_path)
            );
        }

        // 初始化结果数据结构
        if (empty($result_data)) {
            $result_data = [
                'unreadable_paths'   => [],
                'not_writable_paths' => [],
            ];
        }

        try {
            // 检查当前目录权限
            if (!is_readable($directory_path)) {
                $result_data['unreadable_paths'][] = $directory_path;
            }
            if (!is_writable($directory_path)) {
                $result_data['not_writable_paths'][] = $directory_path;
            }

            // 仅当目录可读时继续检查内容
            if (is_readable($directory_path)) {
                $directory_handle = opendir($directory_path);

                while (($entry_name = readdir($directory_handle)) !== false) {
                    // 跳过特殊目录项
                    if ($entry_name === '.' || $entry_name === '..') {
                        continue;
                    }

                    $full_path = $directory_path . DIRECTORY_SEPARATOR . $entry_name;

                    // 检查是否在排除目录列表中
                    $should_skip = false;
                    foreach ($excluded_directories as $excluded_dir) {
                        if (str_contains($full_path, $excluded_dir)) {
                            $should_skip = true;
                            break;
                        }
                    }

                    if ($should_skip) {
                        continue;
                    }

                    // 递归处理子目录
                    if (is_dir($full_path)) {
                        $result_data = check_directory_permissions(
                            $full_path,
                            $result_data,
                            $excluded_directories
                        );
                    } // 处理文件权限
                    else {
                        if (!is_readable($full_path)) {
                            $result_data['unreadable_paths'][] = $full_path;
                        }
                        if (!is_writable($full_path)) {
                            $result_data['not_writable_paths'][] = $full_path;
                        }
                    }
                }

                closedir($directory_handle);
            }

            return $result_data;

        } catch (Exception $exception) {
            // 异常情况下标记当前目录为不可访问
            $result_data['unreadable_paths'][]   = $directory_path;
            $result_data['not_writable_paths'][] = $directory_path;
            return $result_data;
        }
    }
}

if (!function_exists('merge_arrays_recursively')) {
    /**
     * 深度合并两个多维数组（递归合并）
     * 规则：
     * 1. 如果键存在且都是数组，则递归合并
     * 2. 如果键存在但类型不同，则用第二个数组的值覆盖
     * 3. 如果键只在第二个数组中存在，则添加新键值对
     *
     * @param array $first_array  基础数组
     * @param array $second_array 要合并的数组
     *
     * @return array 合并后的新数组
     */
    function merge_arrays_recursively(array $first_array, array $second_array): array
    {
        // 创建结果数组的副本以避免修改原始数据
        $merged_result = $first_array;

        foreach ($second_array as $key => $value) {
            // 键存在于两个数组中
            if (array_key_exists($key, $merged_result)) {
                // 两个值都是数组 - 递归合并
                if (is_array($merged_result[$key]) && is_array($value)) {
                    $merged_result[$key] = merge_arrays_recursively(
                        $merged_result[$key],
                        $value
                    );
                } // 值类型不同或不是数组 - 覆盖
                else {
                    $merged_result[$key] = $value;
                }
            } // 键只存在于第二个数组中 - 添加新键值对
            else {
                $merged_result[$key] = $value;
            }
        }

        return $merged_result;
    }
}
if (!function_exists('resource_path')) {
    /**
     * 获取资源目录路径
     *
     * @param string $path 可选子路径
     * @return string 完整文件系统路径
     */
    function resource_path(string $path = ''): string
    {
        // 规范化路径分隔符
        $subPath = $path ? DIRECTORY_SEPARATOR . trim($path, '/\\') : '';

        // 拼接基础路径和资源目录
        return base_path('resources' . $subPath);
    }
}

if (!function_exists('str_starts_with')) {
    /**
     * 检查字符串是否以指定前缀开始
     *
     * @param string $haystack 要检查的字符串
     * @param string $needle 要查找的前缀
     * @return bool
     */
    function str_starts_with(string $haystack, string $needle): bool
    {
        return substr($haystack, 0, strlen($needle)) === $needle;
    }
}

if (!function_exists('str_ends_with')) {
    /**
     * 检查字符串是否以指定后缀结束
     *
     * @param string $haystack 要检查的字符串
     * @param string $needle 要查找的后缀
     * @return bool
     */
    function str_ends_with(string $haystack, string $needle): bool
    {
        $length = strlen($needle);
        if ($length === 0) {
            return true;
        }
        return substr($haystack, -$length) === $needle;
    }
}
