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

/**
 * 模板资源操作 Trait
 */
trait TemplateTrait
{
    /**
     * 复制模板资源到目标目录
     *
     * 部署到三端：
     * - admin    → template/mono/apps/admin/src/plugin/{name}
     * - platform → template/mono/apps/platform/src/plugin/{name}
     * - web      → template/web/app/plugin/{name}
     */
    protected function copyTemplates(): void
    {
        $templateDir = $this->pluginPath . '/resource/template';

        if (!is_dir($templateDir)) {
            $this->output("📂 No template directory found, skipping...");
            return;
        }

        $this->output("📂 Copying templates from {$templateDir}...");

        $this->copyTemplateDir('admin');
        $this->copyTemplateDir('platform');
        $this->copyTemplateDir('web');
    }

    /**
     * 复制单个端的模板
     */
    protected function copyTemplateDir(string $endpoint): void
    {
        $resourceDir = $this->getConfig('resource.template', 'template');
        $targetBase = $this->getConfig("template.{$endpoint}");

        $sourceDir = $this->pluginPath . '/resource/' . $resourceDir . '/' . $endpoint;
        $targetDir = $this->getProjectRoot() . '/' . $targetBase . '/' . $this->pluginName;

        if (!is_dir($sourceDir)) {
            $this->output("  ⚠️ {$endpoint}: Template source not found");
            return;
        }

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

        $this->deleteTemplateDir('admin');
        $this->deleteTemplateDir('platform');
        $this->deleteTemplateDir('web');
    }

    /**
     * 删除单个端模板
     */
    protected function deleteTemplateDir(string $endpoint): void
    {
        $targetBase = $this->getConfig("template.{$endpoint}");

        $targetDir = $this->getProjectRoot() . '/' . $targetBase . '/' . $this->pluginName;

        if (!is_dir($targetDir)) {
            $this->output("  ⚠️ {$endpoint}: Template directory not found");
            return;
        }

        $this->recurseDelete($targetDir);

        $this->output("  ✅ {$endpoint}: Templates deleted");
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
