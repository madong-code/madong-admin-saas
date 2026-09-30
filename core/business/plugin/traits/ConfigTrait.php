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
 * 配置管理 Trait
 * 实现配置的获取、合并和覆盖机制
 */
trait ConfigTrait
{
    /**
     * 应用配置缓存（实例级别，避免 Workerman 长进程下的静态环境污染）
     */
    private ?array $_appConfigCache = null;

    /**
     * 插件信息缓存（实例级别）
     */
    private ?array $_pluginInfoCache = null;

    /**
     * 获取应用配置
     */
    protected function getAppConfig(): array
    {
        if ($this->_appConfigCache === null) {
            // Trait 中 __DIR__ 是 core/business/plugin/traits，需要两级目录到达 core/business/config
            $configFile = dirname(dirname(__DIR__)) . '/config/plugin.php';
            if (file_exists($configFile)) {
                // 清除 OPcache 缓存，确保读取最新配置（安装时模板路径等变更能立即生效）
                if (function_exists('opcache_invalidate')) {
                    opcache_invalidate($configFile, true);
                }
                $this->_appConfigCache = include $configFile;
            } else {
                $this->_appConfigCache = [];
            }
        }
        
        return $this->_appConfigCache;
    }
    
    /**
     * 获取配置项（合并 appConfig + info.php 配置）
     * info.php 中的配置优先级更高
     */
    protected function getConfig(string $key, mixed $default = null): mixed
    {
        // 先从 appConfig 获取
        $appConfigValue = $this->getAppConfigValue($key);
        
        // 再从插件 info.php 获取（覆盖 appConfig）
        $pluginConfigValue = $this->getPluginConfigValue($key);
        
        // 插件配置优先
        if ($pluginConfigValue !== null) {
            return $pluginConfigValue;
        }
        
        return $appConfigValue !== null ? $appConfigValue : $default;
    }
    
    /**
     * 从 appConfig 获取配置值
     */
    private function getAppConfigValue(string $key): mixed
    {
        $keys = explode('.', $key);
        $value = $this->appConfig;
        
        foreach ($keys as $k) {
            if (!isset($value[$k])) {
                return null;
            }
            $value = $value[$k];
        }
        
        return $value;
    }
    
    /**
     * 从插件 info.php 获取配置值
     */
    private function getPluginConfigValue(string $key): mixed
    {
        if ($this->_pluginInfoCache === null) {
            $infoFile = $this->getPluginInfoFile();
            $this->_pluginInfoCache = file_exists($infoFile) ? include $infoFile : [];
        }
        
        if (empty($this->_pluginInfoCache)) {
            return null;
        }
        
        $keys = explode('.', $key);
        $value = $this->_pluginInfoCache;
        
        foreach ($keys as $k) {
            if (!isset($value[$k])) {
                return null;
            }
            $value = $value[$k];
        }
        
        return $value;
    }

    /**
     * 获取插件信息配置文件路径
     */
    protected function getPluginInfoFile(): string
    {
        return $this->pluginPath . '/config/info.php';
    }

    /**
     * 获取插件信息（包含隐式默认配置）
     * info.php 中的配置会覆盖默认配置
     */
    protected function getPluginInfo(): array
    {
        // 先获取显式配置（info.php）
        $infoFile = $this->getPluginInfoFile();
        $explicitConfig = [];

        if (file_exists($infoFile)) {
            $explicitConfig = include $infoFile;
            $explicitConfig = is_array($explicitConfig) ? $explicitConfig : [];
        }

        // 合并隐式默认配置
        return array_merge($this->getImplicitConfig(), $explicitConfig);
    }

    /**
     * 获取隐式默认配置
     */
    protected function getImplicitConfig(): array
    {
        return [
            'dependencies'  => [],
            'require'       => [
                'composer'  => [],
            ],
            'npm_require'   => [
                'admin'    => [],
                'platform' => [],
                'web'      => [],
            ],
            'supports'      => [
                'backend'   => true,
                'frontend'  => true,
                'admin'     => true,
                'web'       => true,
            ],
        ];
    }

    /**
     * 获取插件配置文件路径
     */
    protected function getPluginConfigFile(): string
    {
        return $this->pluginPath . '/config/installed.php';
    }

    /**
     * 保存插件安装状态
     */
    protected function savePluginConfig(string $version): void
    {
        $configFile = $this->getPluginConfigFile();
        $configDir = dirname($configFile);

        if (!is_dir($configDir)) {
            mkdir($configDir, 0755, true);
        }

        $existingConfig = [];
        if (file_exists($configFile)) {
            $existingConfig = include $configFile;
            $existingConfig = is_array($existingConfig) ? $existingConfig : [];
        }

        $config = array_merge($existingConfig, [
            'name'          => $this->getPluginName(),
            'version'       => $version,
            'installed_at'  => date('Y-m-d H:i:s'),
            'status'        => 'installed',
        ]);

        $content = "<?php\n\n/**\n * 插件安装状态配置\n * 此文件由系统自动生成，请勿手动修改\n */\n\nreturn " . var_export($config, true) . ";\n";

        file_put_contents($configFile, $content);

        $this->output("📝 Plugin config saved: {$configFile}");
    }

    /**
     * 获取插件安装状态
     */
    protected function getPluginConfig(): ?array
    {
        $configFile = $this->getPluginConfigFile();

        if (!file_exists($configFile)) {
            return null;
        }

        $config = include $configFile;
        return is_array($config) ? $config : null;
    }

    /**
     * 检查插件是否已安装
     */
    public function isInstalled(): bool
    {
        $config = $this->getPluginConfig();
        return $config !== null && ($config['status'] ?? '') === 'installed';
    }

    /**
     * 获取插件版本
     */
    public function getVersion(): ?string
    {
        $config = $this->getPluginConfig();
        return $config['version'] ?? null;
    }

    /**
     * 删除插件配置文件
     */
    protected function removePluginConfig(): void
    {
        $configFile = $this->getPluginConfigFile();

        if (file_exists($configFile)) {
            unlink($configFile);
            $this->output("📝 Plugin config removed: {$configFile}");
        }
    }
}
