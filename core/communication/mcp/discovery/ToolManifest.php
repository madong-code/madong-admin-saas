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

namespace core\communication\mcp\discovery;

use core\communication\mcp\attribute\McpTool;
use core\communication\mcp\security\McpUser;
use Symfony\Component\Finder\Finder;

/**
 * MCP 工具清单：扫描 + 反射 #[McpTool] + 缓存 + 权限过滤
 *
 * 缓存策略：每次 load 重新扫描文件列表并计算 mtime 指纹（快），指纹命中则直接
 * include 缓存清单（免反射）；新增/修改/删除工具文件自动失效。
 */
final class ToolManifest
{
    /**
     * 加载全部工具清单
     *
     * @param bool $rebuild 强制重建（忽略指纹缓存）
     * @return array<int, array{class:string,method:string,name:string,title:string,description:string,inputSchema:array,permission:string|array|false|null,source:string}>
     */
    public function load(bool $rebuild = false): array
    {
        $config = (array) config('mcp.discovery', []);
        $files = $this->scanFiles($this->resolveDirs((array) ($config['dirs'] ?? [])));
        $fingerprint = $this->fingerprint($files);
        $cacheFile = (string) ($config['manifest'] ?: runtime_path('mcp/manifest.php'));

        if (!$rebuild) {
            $cached = $this->readCache($cacheFile, $fingerprint);
            if ($cached !== null) {
                return $cached;
            }
        }

        $entries = $this->reflect($files);
        $this->writeCache($cacheFile, $fingerprint, $entries);
        return $entries;
    }

    /**
     * 按 McpUser 过滤可见工具（可见性 = 可执行性：未注册即不可调用）
     */
    public function filterFor(array $entries, ?McpUser $user): array
    {
        return array_values(array_filter(
            $entries,
            fn (array $entry): bool => $this->permitted($entry['permission'], $user),
        ));
    }

    private function permitted(string|array|false|null $permission, ?McpUser $user): bool
    {
        if ($permission === null) {
            return true;
        }
        if ($permission === false) {
            return $user !== null;
        }
        return $user !== null && $user->can($permission);
    }

    /**
     * @return string[] 目录列表（绝对路径，经 realpath 规范化，消除 /../ 段）
     */
    private function resolveDirs(array $dirs): array
    {
        $resolved = [];
        foreach ($dirs as $dir) {
            $dir = (string) $dir;
            if (preg_match('/[*?\[\]]/', $dir)) {
                // glob 目录：相对路径基于 base_path 展开
                $pattern = is_dir($dir) ? $dir : base_path($dir);
                foreach (glob($pattern) ?: [] as $path) {
                    if (is_dir($path)) {
                        $resolved[] = (string) realpath($path);
                    }
                }
                continue;
            }
            if (is_dir($dir)) {
                $resolved[] = (string) realpath($dir);
                continue;
            }
            $candidate = base_path($dir);
            if (is_dir($candidate)) {
                $resolved[] = (string) realpath($candidate);
            }
        }
        return array_values(array_unique($resolved));
    }

    /**
     * @return string[] PHP 文件列表（绝对路径）
     */
    private function scanFiles(array $dirs): array
    {
        if ($dirs === []) {
            return [];
        }
        $files = [];
        $finder = (new Finder())->files()->name('*.php')->in($dirs)->sortByName();
        foreach ($finder as $file) {
            $files[] = $file->getPathname();
        }
        return $files;
    }

    private function fingerprint(array $files): string
    {
        $map = [];
        foreach ($files as $file) {
            $map[$file] = @filemtime($file) ?: 0;
        }
        return hash('sha256', serialize($map));
    }

    /**
     * 由文件路径推导类名（根 composer.json psr-4 "" => "./" 保证路径映射），不存在或不可加载返回 null
     */
    private function classFor(string $file): ?string
    {
        $base = str_replace('\\', '/', base_path());
        $relative = str_replace('\\', '/', $file);
        if (str_starts_with($relative, $base . '/')) {
            $relative = substr($relative, strlen($base) + 1);
        }
        $class = str_replace('/', '\\', preg_replace('/\.php$/', '', $relative));
        if ($class === '' || !class_exists($class)) {
            return null;
        }
        return $class;
    }

    /**
     * @param string[] $files
     * @return array<int, array{class:string,method:string,name:string,title:string,description:string,inputSchema:array,permission:string|array|false|null,source:string}>
     */
    private function reflect(array $files): array
    {
        $entries = [];
        foreach ($files as $file) {
            $class = $this->classFor($file);
            if ($class === null) {
                continue;
            }
            $reflection = new \ReflectionClass($class);
            if ($reflection->isAbstract() || $reflection->isInterface() || $reflection->isEnum()) {
                continue;
            }

            foreach ($reflection->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
                $attributes = $method->getAttributes(McpTool::class);
                if ($attributes === []) {
                    continue;
                }
                /** @var McpTool $tool */
                $tool = $attributes[0]->newInstance();
                $entries[] = [
                    'class' => $class,
                    'method' => $method->getName(),
                    'name' => $tool->name,
                    'title' => $tool->title,
                    'description' => $tool->description,
                    'inputSchema' => $tool->inputSchema,
                    'permission' => $tool->permission,
                    'source' => $this->sourceOf($file),
                ];
            }
        }

        // 同名工具以先扫描者为准（目录顺序：app/mcp -> plugin/*/app/mcp -> 内置 tool）
        $unique = [];
        foreach ($entries as $entry) {
            $unique[$entry['name']] ??= $entry;
        }
        return array_values($unique);
    }

    private function sourceOf(string $file): string
    {
        $base = str_replace('\\', '/', base_path());
        $relative = str_replace('\\', '/', $file);
        if (str_starts_with($relative, $base . '/')) {
            $relative = substr($relative, strlen($base) + 1);
        }
        $segments = explode('/', $relative);
        if ($segments[0] === 'plugin' && isset($segments[1])) {
            return 'plugin/' . $segments[1];
        }
        return $segments[0];
    }

    private function readCache(string $cacheFile, string $fingerprint): ?array
    {
        if (!is_file($cacheFile)) {
            return null;
        }
        $data = include $cacheFile;
        if (!is_array($data)
            || ($data['fingerprint'] ?? null) !== $fingerprint
            || !isset($data['tools'])
            || !is_array($data['tools'])
        ) {
            return null;
        }
        return $data['tools'];
    }

    private function writeCache(string $cacheFile, string $fingerprint, array $entries): void
    {
        $dir = dirname($cacheFile);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        $export = "<?php\n// MCP 工具清单缓存（自动生成，请勿手工修改）\nreturn " . var_export([
            'fingerprint' => $fingerprint,
            'tools' => $entries,
        ], true) . ";\n";
        @file_put_contents($cacheFile, $export);
    }
}
