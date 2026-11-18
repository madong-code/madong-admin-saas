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
namespace core\business\plugin\traits;

use core\business\install\InstallConfigResolver;

/**
 * 模板资源操作 Trait
 */
trait TemplateTrait
{
    /** 模板分发端点：admin/platform 按模式解析，web 通用 */
    private const TEMPLATE_ENDPOINTS = ['admin', 'platform', 'web'];

    /** @var InstallConfigResolver|null */
    private ?InstallConfigResolver $_installConfigResolver = null;

    /**
     * 获取安装配置解析器（延迟实例化）
     */
    private function installConfigResolver(): InstallConfigResolver
    {
        if ($this->_installConfigResolver === null) {
            $this->_installConfigResolver = new InstallConfigResolver();
        }
        return $this->_installConfigResolver;
    }

    /**
     * 解析单个端点的落盘目录（绝对路径，不含插件名）
     *
     * 优先级：
     *  1) 插件 info.php 显式配置 template.{endpoint}（向后兼容）
     *  2) config/install.php 按运行模式解析（mono/separated）
     *  3) 回退应用配置 getConfig('template.{endpoint}')
     *
     * @return string 失败返回空字符串
     */
    private function resolveTemplateTargetBase(string $endpoint): string
    {
        // 1) 插件 info.php 显式覆盖
        $infoFile = $this->pluginPath . '/config/info.php';
        if (is_file($infoFile)) {
            $info = include $infoFile;
            if (is_array($info) && isset($info['template'][$endpoint])
                && is_string($info['template'][$endpoint]) && $info['template'][$endpoint] !== '') {
                return $this->getProjectRoot() . '/' . trim($info['template'][$endpoint], '/');
            }
        }

        // 2) 按运行模式从 config/install.php 解析（模板根 + plugin_dir）
        $resolver = $this->installConfigResolver();
        $path = $resolver->templatePath($endpoint, $resolver->resolveMode($this->getInstallMode()));
        if ($path !== '') {
            $pluginDir = $resolver->templateMeta($endpoint)['plugin_dir'] ?? 'src/plugin';
            return rtrim($path . '/' . trim((string) $pluginDir, '/'), '/');
        }

        // 3) 回退应用配置
        $targetBase = $this->getConfig("template.{$endpoint}");
        return is_string($targetBase) && $targetBase !== ''
            ? $this->getProjectRoot() . '/' . trim($targetBase, '/')
            : '';
    }

    /**
     * 复制模板资源到目标目录（路径按运行模式解析）
     *
     * - mono：      admin/platform → template/mono/apps/{x}/src/plugin/{name}
     * - separated： admin/platform → template/{x}/src/plugin/{name}
     * - web（通用）：template/web/src/plugin/{name}
     */
    protected function copyTemplates(): void
    {
        $templateDir = $this->pluginPath . '/resource/template';

        if (!is_dir($templateDir)) {
            $this->output("📂 No template directory found, skipping...");
            return;
        }

        $this->output("📂 Copying templates from {$templateDir}...");

        foreach (self::TEMPLATE_ENDPOINTS as $endpoint) {
            $this->copyTemplateDir($endpoint);
        }
    }

    /**
     * 复制单个端的模板
     */
    protected function copyTemplateDir(string $endpoint): void
    {
        $resourceDir = $this->getConfig('resource.template', 'template');
        $sourceDir = $this->pluginPath . '/resource/' . $resourceDir . '/' . $endpoint;

        if (!is_dir($sourceDir)) {
            $this->output("  ⚠️ {$endpoint}: Template source not found");
            return;
        }

        $targetBase = $this->resolveTemplateTargetBase($endpoint);
        if ($targetBase === '') {
            $this->output("  ⚠️ {$endpoint}: Template target path not resolved");
            return;
        }
        $targetDir = $targetBase . '/' . $this->pluginName;

        if (!is_dir(dirname($targetDir))) {
            mkdir(dirname($targetDir), 0755, true);
        }

        $this->recurseCopy($sourceDir, $targetDir);

        $this->output("  ✅ {$endpoint}: Templates copied to {$targetDir}");
    }

    /**
     * 递归复制目录
     */
    protected function recurseCopy(string $source, string $target): void
    {
        if (!is_dir($source)) {
            return;
        }

        if (!is_dir($target)) {
            mkdir($target, 0755, true);
        }

        $files = scandir($source);
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }

            $sourcePath = $source . '/' . $file;
            $targetPath = $target . '/' . $file;

            if (is_dir($sourcePath)) {
                $this->recurseCopy($sourcePath, $targetPath);
            } else {
                copy($sourcePath, $targetPath);
            }
        }
    }

    /**
     * 删除插件模板资源
     */
    protected function deleteTemplates(): void
    {
        $this->output("🗑️ Deleting plugin templates...");

        // 同时清理所有模式的落盘目录，避免切换模式后旧目录变成幽灵目录：
        // 若只按当前模式删，切到另一模式安装会残留上一种模式的副本。
        $resolver = $this->installConfigResolver();
        $mode = $resolver->resolveMode($this->getInstallMode());
        foreach (self::TEMPLATE_ENDPOINTS as $endpoint) {
            $base = $this->resolveTemplateTargetBase($endpoint);
            if ($base !== '') {
                $this->deleteTemplatePath($base . '/' . $this->pluginName, $endpoint);
            }
        }

        // 清理非当前模式的其它模式路径（仅当落盘目录确实存在时）
        foreach ($resolver->modes() as $modeKey => $_) {
            if ($modeKey === $mode) {
                continue;
            }
            foreach (self::TEMPLATE_ENDPOINTS as $endpoint) {
                $path = $resolver->templatePath($endpoint, $modeKey);
                if ($path === '') {
                    continue;
                }
                // 与安装落盘保持一致：必须拼接 plugin_dir，否则只删到模板根，src/plugin 下副本残留
                $pluginDir = $resolver->templateMeta($endpoint)['plugin_dir'] ?? 'src/plugin';
                $targetDir = rtrim($path . '/' . trim((string) $pluginDir, '/') . '/' . $this->pluginName, '/');
                $this->deleteTemplatePath($targetDir, $endpoint . "@{$modeKey}");
            }
        }
    }

    /**
     * 删除单个端的模板目录（按路径）
     */
    private function deleteTemplatePath(string $targetDir, string $label): void
    {
        if (!is_dir($targetDir)) {
            return;
        }
        $this->recurseDelete($targetDir);
        $this->output("  ✅ {$label}: Templates deleted");
    }

    /**
     * 删除单个端模板（保留给子类/兼容调用，按当前模式解析）
     */
    protected function deleteTemplateDir(string $endpoint): void
    {
        $targetBase = $this->resolveTemplateTargetBase($endpoint);
        if ($targetBase === '') {
            return;
        }
        $this->deleteTemplatePath($targetBase . '/' . $this->pluginName, $endpoint);
    }

    /**
     * 递归删除目录
     */
    protected function recurseDelete(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = scandir($dir);
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }

            $path = $dir . '/' . $file;
            if (is_dir($path)) {
                $this->recurseDelete($path);
            } else {
                unlink($path);
            }
        }

        rmdir($dir);
    }

    /**
     * 导入模板（子类可重写）
     */
    protected function importTemplates(): void
    {
        $this->copyTemplates();
    }

    /**
     * 获取项目根目录
     */
    protected function getProjectRoot(): string
    {
        return dirname(base_path());
    }

    /**
     * 获取前端基础目录
     * 前端代码统一在 frontend 目录下
     */
    protected function getFrontendPath(): string
    {
        return $this->getProjectRoot() . DIRECTORY_SEPARATOR . 'frontend';
    }

    /**
     * 获取前端指定项目目录
     *
     * @param string $frontendType 前端类型（admin/web/uni-app）
     * @return string 前端项目目录
     */
    protected function getFrontendProjectPath(string $frontendType): string
    {
        return $this->getFrontendPath() . DIRECTORY_SEPARATOR . $frontendType;
    }
}
