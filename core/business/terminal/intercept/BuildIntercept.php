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
namespace core\business\terminal\intercept;

use core\foundation\tool\Sse;
use support\Log;

/**
 * 构建拦截器类
 * 提供构建相关的前置和后置方法
 */
class BuildIntercept implements InterceptInterface
{
    protected string $uuid;

    public function __construct(string $uuid = '')
    {
        $this->uuid = $uuid;
    }

    /**
     * 替换路径中的变量
     *
     * @param string $path 路径
     *
     * @return string
     */
    protected function replacePathVariables(string $path): string
    {
        // 后端根目录（server目录）
        $backendRoot = base_path();
        // 项目根目录（包含server、admin、web等目录的父目录）
        $projectRoot = dirname($backendRoot);
        // 前端模板monorepo目录
        $templateRoot = $projectRoot . DIRECTORY_SEPARATOR . 'template' . DIRECTORY_SEPARATOR . 'mono';
        
        $variables = [
            '{backend_root}' => $backendRoot,
            '{project_root}' => $projectRoot,
            '{template_root}' => $templateRoot,
        ];
        
        foreach ($variables as $variable => $value) {
            $path = str_replace($variable, $value, $path);
        }
        
        return $path;
    }

    /**
     * 前置处理
     *
     * @return \Generator
     */
    public function before(): \Generator
    {
        yield Sse::progress('开始构建前端项目...', 0, [], $this->uuid);
        yield Sse::progress('检查构建环境...', 1, [], $this->uuid);
        yield Sse::progress('清理旧的构建文件...', 2, [], $this->uuid);
    }

    /**
     * 后置处理
     *
     * @param string $commandKey 命令键
     * @param int $exitCode 退出码
     *
     * @return \Generator
     */
    public function after(string $commandKey = '', int $exitCode = 0): \Generator
    {
        if ($exitCode !== 0) {
            yield Sse::progress('构建失败，跳过迁移部署', 92, [], $this->uuid);
            return;
        }

        yield Sse::progress('构建完成，开始迁移部署...', 92, [], $this->uuid);

        // 解析命令键，获取前端类型
        $keyParts = explode('.', $commandKey);
        $frontendType = $keyParts[1] ?? '';

        // 执行迁移
        if (!empty($frontendType)) {
            yield from $this->migrateFrontendBuild($frontendType, $commandKey);
        }

        yield Sse::progress('部署完成！', 98, [], $this->uuid);
    }

    /**
     * 迁移前端构建文件
     *
     * @param string $frontendType 前端类型（admin/web/h5/app）
     * @param string $commandKey 命令键
     *
     * @return \Generator
     */
    protected function migrateFrontendBuild(string $frontendType, string $commandKey = ''): \Generator
    {
        $config = config('terminal.frontend_programs', []);
        
        if (!isset($config[$frontendType]) || !$config[$frontendType]['enabled']) {
            yield Sse::progress("未配置 {$frontendType} 前端程序", 93, [], $this->uuid);
            return;
        }

        // 处理uni-app等多平台程序
        $programConfig = $config[$frontendType];
        if ($frontendType === 'uni-app') {
            // 从命令键中提取平台信息
            $keyParts = explode('.', $commandKey);
            $platform = $keyParts[2] ?? $programConfig['default_platform'] ?? 'h5';
            
            if (isset($programConfig['platforms'][$platform])) {
                $programConfig = array_merge($programConfig, $programConfig['platforms'][$platform]);
            } else {
                yield Sse::progress("未配置 {$frontendType} 的 {$platform} 平台", 93, [], $this->uuid);
                return;
            }
        }

        $sourceDir = $programConfig['source_dir'] ?? '';
        $targetDir = $programConfig['target_dir'] ?? '';
        $copyMappings = $programConfig['copy_mappings'] ?? [];
        $cleanTarget = $programConfig['clean_target'] ?? true;
        $preserveFiles = $programConfig['preserve_files'] ?? [];
        $copyOptions = $programConfig['copy_options'] ?? [];

        // 替换路径变量
        $sourceDir = $this->replacePathVariables($sourceDir);
        $targetDir = $this->replacePathVariables($targetDir);

        if (empty($sourceDir) || empty($targetDir)) {
            yield Sse::progress("{$frontendType} 前端程序配置不完整", 93, [], $this->uuid);
            return;
        }

        yield Sse::progress("开始迁移 {$frontendType} 前端构建文件...", 93, [], $this->uuid);

        try {
            // 复制构建文件
            if ($this->copyBuildFiles($sourceDir, $targetDir, $copyMappings, $cleanTarget, $preserveFiles, $copyOptions)) {
                yield Sse::progress("{$frontendType} 前端构建文件迁移成功", 95, [], $this->uuid);
            } else {
                yield Sse::progress("{$frontendType} 前端构建文件迁移失败", 95, [], $this->uuid);
            }
        } catch (\Exception $e) {
            yield Sse::progress("{$frontendType} 前端构建文件迁移失败: " . $e->getMessage(), 95, [], $this->uuid);
            Log::error("Frontend build migration failed: " . $e->getMessage());
        }
    }

    /**
     * 获取构建输出目录
     *
     * @param string $frontendType
     *
     * @return string
     */
    protected function getBuildOutputDir(string $frontendType): string
    {
        $config = config('terminal.frontend_programs', []);
        
        if (isset($config[$frontendType]) && isset($config[$frontendType]['source_dir'])) {
            return $this->replacePathVariables($config[$frontendType]['source_dir']);
        }
        
        // 兼容旧配置
        $projectRoot = dirname(base_path());
        
        $monoRoot    = $projectRoot . DIRECTORY_SEPARATOR . 'template' . DIRECTORY_SEPARATOR . 'mono';
        $templateDir = $projectRoot . DIRECTORY_SEPARATOR . 'template';
        $outputDirs = [
            'admin'    => $monoRoot . '/apps/admin/dist',
            'platform' => $monoRoot . '/apps/platform/dist',
            'web'      => $templateDir . '/web/.output/public',
            'h5'       => $templateDir . '/uni-app/dist/build/h5',
            'app'      => $templateDir . '/uni-app/dist/build/app',
        ];

        return $outputDirs[$frontendType] ?? '';
    }

    /**
     * 获取目标目录
     *
     * @param string $frontendType
     *
     * @return string
     */
    protected function getTargetDir(string $frontendType): string
    {
        $config = config('terminal.frontend_programs', []);
        
        if (isset($config[$frontendType]) && isset($config[$frontendType]['target_dir'])) {
            return $this->replacePathVariables($config[$frontendType]['target_dir']);
        }
        
        // 兼容旧配置
        $backendRoot = base_path();
        
        $targetDirs = [
            'admin'    => $backendRoot . '/public/admin',
            'platform' => $backendRoot . '/public/platform',
            'web'      => $backendRoot . '/public',
            'h5'       => $backendRoot . '/public/h5',
            'app'      => $backendRoot . '/public/app',
        ];

        return $targetDirs[$frontendType] ?? '';
    }

    /**
     * 复制构建文件
     *
     * @param string $sourceDir 源目录
     * @param string $targetDir 目标目录
     * @param array $copyMappings 复制映射
     * @param bool $cleanTarget 是否清理目标目录
     * @param array $preserveFiles 保留的文件
     * @param array $copyOptions 复制选项
     *
     * @return bool
     */
    protected function copyBuildFiles(string $sourceDir, string $targetDir, array $copyMappings = [], bool $cleanTarget = true, array $preserveFiles = [], array $copyOptions = []): bool
    {
        if (!is_dir($sourceDir)) {
            return false;
        }

        // 确保目标目录存在
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }

        // 清理目标目录（如果需要）
        if ($cleanTarget && is_dir($targetDir)) {
            $this->cleanDirectory($targetDir, $preserveFiles);
        }

        // 如果没有配置复制映射，默认复制所有文件
        if (empty($copyMappings)) {
            $copyMappings = ['*' => '.'];
        }

        // 执行复制
        foreach ($copyMappings as $source => $target) {
            $sourcePath = $sourceDir . DIRECTORY_SEPARATOR . $source;
            $targetPath = $targetDir . DIRECTORY_SEPARATOR . $target;

            // 确保目标路径存在
            if (!is_dir($targetPath)) {
                mkdir($targetPath, 0755, true);
            }

            // 处理通配符复制
            if (str_contains($source, '*')) {
                // 转换路径分隔符为正斜杠，确保 glob 函数在 Windows 上正常工作
                $globPath = str_replace(DIRECTORY_SEPARATOR, '/', $sourcePath);
                $files = glob($globPath);
                if (!empty($files)) {
                    foreach ($files as $file) {
                        $destFile = $targetPath . DIRECTORY_SEPARATOR . basename($file);
                        if (is_dir($file)) {
                            // 递归复制目录
                            $this->copyDirectory($file, $destFile);
                        } else {
                            // 复制文件
                            copy($file, $destFile);
                        }
                    }
                }
            } elseif (is_dir($sourcePath)) {
                // 复制目录
                $this->copyDirectory($sourcePath, $targetPath);
            } else {
                // 复制单个文件
                if (file_exists($sourcePath)) {
                    copy($sourcePath, $targetPath);
                }
            }
        }

        return true;
    }

    /**
     * 复制目录
     *
     * @param string $source 源目录
     * @param string $target 目标目录
     *
     * @return bool
     */
    protected function copyDirectory(string $source, string $target): bool
    {
        if (!is_dir($source)) {
            return false;
        }

        // 创建目标目录
        if (!is_dir($target)) {
            mkdir($target, 0755, true);
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($source, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            $sourcePath = $item->getPathname();
            $targetPath = $target . DIRECTORY_SEPARATOR . $iterator->getSubPathName();

            if ($item->isDir()) {
                if (!is_dir($targetPath)) {
                    mkdir($targetPath, 0755, true);
                }
            } else {
                copy($sourcePath, $targetPath);
            }
        }

        return true;
    }

    /**
     * 清理目录
     *
     * @param string $directory 目录路径
     * @param array $preserveFiles 保留的文件
     */
    protected function cleanDirectory(string $directory, array $preserveFiles = []): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $files = scandir($directory);
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }

            $path = $directory . DIRECTORY_SEPARATOR . $file;
            if (in_array($file, $preserveFiles)) {
                continue;
            }

            if (is_dir($path)) {
                $this->deleteDirectory($path);
            } else {
                unlink($path);
            }
        }
    }

    /**
     * 删除目录
     *
     * @param string $directory 目录路径
     */
    protected function deleteDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $files = scandir($directory);
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }

            $path = $directory . DIRECTORY_SEPARATOR . $file;
            if (is_dir($path)) {
                $this->deleteDirectory($path);
            } else {
                unlink($path);
            }
        }

        rmdir($directory);
    }
}
