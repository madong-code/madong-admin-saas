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
 * 依赖安装 Trait
 */
trait DependencyTrait
{
    /**
     * 合并 Composer 依赖（后端）
     */
    protected function installComposerDeps(): void
    {
        $pluginInfo = $this->getPluginInfo();
        $require = $pluginInfo['require']['composer'] ?? [];

        if (empty($require)) {
            $this->output("📦 No Composer dependencies to merge");
            return;
        }

        $config = $this->getConfig('dependencies.backend', []);
        if (empty($config['enabled'])) {
            $this->output("📦 Composer dependency merge disabled");
            return;
        }

        $this->output("📦 Merging Composer dependencies...");

        $composerFile = base_path('composer.json');
        if (!file_exists($composerFile)) {
            $this->output("  ⚠️ composer.json not found");
            return;
        }

        $composerContent = file_get_contents($composerFile);
        $composer = json_decode($composerContent, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->output("  ⚠️ Invalid composer.json format");
            return;
        }

        $merged = false;
        
        // 合并 require
        if (!empty($config['merge_require'])) {
            foreach ($require as $package => $version) {
                if (!isset($composer['require'][$package])) {
                    $composer['require'][$package] = $version;
                    $merged = true;
                    $this->output("  ➕ Added to require: {$package}:{$version}");
                }
            }
        }

        // 保存
        if ($merged) {
            file_put_contents($composerFile, json_encode($composer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
            $this->output("  ✅ Composer dependencies merged. Run 'composer install' manually.");
        } else {
            $this->output("  ℹ️ No new dependencies to merge");
        }
    }

    /**
     * 合并 NPM 依赖（前端）
     */
    protected function installNpmDeps(): void
    {
        $pluginInfo = $this->getPluginInfo();
        $npmRequire = $pluginInfo['npm_require'] ?? [];

        if (empty($npmRequire)) {
            $this->output("📦 No NPM dependencies to merge");
            return;
        }

        $projectRoot = $this->getProjectRoot();

        // 合并 Admin 端依赖
        $adminConfig = $this->getConfig('dependencies.admin', []);
        if (!empty($adminConfig['enabled'])) {
            $adminPath = $this->getFrontendProjectPath('admin');
            $this->mergeNpmDeps($npmRequire['admin'] ?? [], $adminPath, 'Admin', $adminConfig);
            $this->mergeNpmDeps($npmRequire['common'] ?? [], $adminPath, 'Admin (common)', $adminConfig);
        }

        // 合并 Platform 端依赖（[新增] 平台管理后台）
        $platformConfig = $this->getConfig('dependencies.platform', []);
        if (!empty($platformConfig['enabled'])) {
            $platformPath = $this->getFrontendProjectPath('platform');
            $this->mergeNpmDeps($npmRequire['platform'] ?? [], $platformPath, 'Platform', $platformConfig);
            $this->mergeNpmDeps($npmRequire['common'] ?? [], $platformPath, 'Platform (common)', $platformConfig);
        }

        // 合并 Web 端依赖
        $webConfig = $this->getConfig('dependencies.web', []);
        if (!empty($webConfig['enabled'])) {
            $webPath = $this->getFrontendProjectPath('web');
            $this->mergeNpmDeps($npmRequire['web'] ?? [], $webPath, 'Web', $webConfig);
            $this->mergeNpmDeps($npmRequire['common'] ?? [], $webPath, 'Web (common)', $webConfig);
        }
    }

    /**
     * 合并 NPM 依赖到 package.json
     */
    protected function mergeNpmDeps(array $packages, string $workDir, string $endpoint, array $config): void
    {
        if (empty($packages) || !is_dir($workDir)) {
            return;
        }

        $packageFile = $workDir . '/package.json';
        if (!file_exists($packageFile)) {
            $this->output("  ⚠️ {$endpoint}: package.json not found");
            return;
        }

        $packageContent = file_get_contents($packageFile);
        $package = json_decode($packageContent, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->output("  ⚠️ {$endpoint}: Invalid package.json format");
            return;
        }

        $merged = false;

        // 合并 dependencies
        if (!empty($config['merge_prod'])) {
            foreach ($packages as $pkg => $version) {
                if (!isset($package['dependencies'][$pkg])) {
                    $package['dependencies'][$pkg] = $version;
                    $merged = true;
                    $this->output("  ➕ {$endpoint}: Added {$pkg}@{$version}");
                }
            }
        }

        // 合并 devDependencies
        if (!empty($config['merge_dev'])) {
            foreach ($packages as $pkg => $version) {
                if (!isset($package['devDependencies'][$pkg])) {
                    $package['devDependencies'][$pkg] = $version;
                    $merged = true;
                    $this->output("  ➕ {$endpoint}: Added dev {$pkg}@{$version}");
                }
            }
        }

        // 保存
        if ($merged) {
            file_put_contents($packageFile, json_encode($package, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
            $this->output("  ✅ {$endpoint}: Dependencies merged. Run 'npm install' manually.");
        }
    }

    /**
     * 移除合并的依赖
     */
    protected function removeMergedDependencies(): void
    {
        $this->output("🗑️ Removing merged dependencies...");

        $pluginInfo = $this->getPluginInfo();

        // 移除 Composer 依赖
        $composerRequire = $pluginInfo['require']['composer'] ?? [];
        if (!empty($composerRequire)) {
            $this->removeComposerDeps($composerRequire);
        }

        // 移除 NPM 依赖
        $npmRequire = $pluginInfo['npm_require'] ?? [];
        if (!empty($npmRequire)) {
            // 前端代码统一在 frontend 目录下
            $this->removeNpmDeps($npmRequire['admin'] ?? [], $this->getFrontendProjectPath('admin'), 'Admin');
            $this->removeNpmDeps($npmRequire['common'] ?? [], $this->getFrontendProjectPath('admin'), 'Admin (common)');

            $this->removeNpmDeps($npmRequire['platform'] ?? [], $this->getFrontendProjectPath('platform'), 'Platform');
            $this->removeNpmDeps($npmRequire['common'] ?? [], $this->getFrontendProjectPath('platform'), 'Platform (common)');

            $this->removeNpmDeps($npmRequire['web'] ?? [], $this->getFrontendProjectPath('web'), 'Web');
            $this->removeNpmDeps($npmRequire['common'] ?? [], $this->getFrontendProjectPath('web'), 'Web (common)');
        }

        $this->output("  ✅ Dependencies removed. Run 'composer install' or 'npm install' to update.");
    }
    
    /**
     * 移除 Composer 依赖
     */
    protected function removeComposerDeps(array $packages): void
    {
        $composerFile = base_path('composer.json');
        if (!file_exists($composerFile)) {
            return;
        }
        
        $composer = json_decode(file_get_contents($composerFile), true);
        
        $removed = false;
        
        foreach ($packages as $package => $version) {
            if (isset($composer['require'][$package])) {
                unset($composer['require'][$package]);
                $removed = true;
                $this->output("  ➖ Removed composer: {$package}");
            }
        }
        
        if ($removed) {
            file_put_contents($composerFile, json_encode($composer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
        }
    }
    
    /**
     * 移除 NPM 依赖
     */
    protected function removeNpmDeps(array $packages, string $workDir, string $endpoint): void
    {
        if (empty($packages) || !is_dir($workDir)) {
            return;
        }
        
        $packageFile = $workDir . '/package.json';
        if (!file_exists($packageFile)) {
            return;
        }
        
        $package = json_decode(file_get_contents($packageFile), true);
        
        $removed = false;
        
        foreach ($packages as $pkg => $version) {
            // 移除 dependencies
            if (isset($package['dependencies'][$pkg])) {
                unset($package['dependencies'][$pkg]);
                $removed = true;
                $this->output("  ➖ {$endpoint}: Removed {$pkg}");
            }
            // 移除 devDependencies
            if (isset($package['devDependencies'][$pkg])) {
                unset($package['devDependencies'][$pkg]);
                $removed = true;
                $this->output("  ➖ {$endpoint}: Removed dev {$pkg}");
            }
        }
        
        if ($removed) {
            file_put_contents($packageFile, json_encode($package, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
        }
    }

    /**
     * 执行 Shell 命令
     */
    protected function runShellCommand(string $command, string $workDir, string $label): void
    {
        if (!is_dir($workDir)) {
            $this->output("  ⚠️ {$label}: Directory not found: {$workDir}");
            return;
        }

        $this->output("  🔧 {$label}: Running '{$command}'");

        try {
            $descriptorspec = [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ];

            $process = proc_open($command, $descriptorspec, $pipes, $workDir);

            if (is_resource($process)) {
                stream_get_contents($pipes[1]);
                $error = stream_get_contents($pipes[2]);

                foreach ($pipes as $pipe) {
                    fclose($pipe);
                }

                $exitCode = proc_close($process);

                if ($exitCode === 0) {
                    $this->output("  ✅ {$label}: Completed successfully");
                } else {
                    $this->output("  ❌ {$label}: Failed with exit code {$exitCode}");
                    if (!empty($error)) {
                        $this->output("     Error: {$error}");
                    }
                }
            }
        } catch (\Throwable $e) {
            $this->output("  ❌ {$label}: Error - {$e->getMessage()}");
        }
    }
}
