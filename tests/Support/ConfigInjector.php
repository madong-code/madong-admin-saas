<?php
declare(strict_types=1);

namespace Tests\Support;

use ReflectionProperty;
use Webman\Config;

/**
 * 测试期配置注入器
 *
 * PHPUnit 环境未启动 Webman，Config::$config 为空，config('install', ...) 只会
 * 返回默认值。本工具通过反射临时注入指定配置项，并在还原时恢复原始状态，
 * 使依赖 config() 的类（如 InstallConfigResolver）可在隔离环境下测试真实配置逻辑。
 *
 * 用法：
 *   $injector = new ConfigInjector();
 *   $injector->set('install', require base_path('config/install.php'));
 *   // ... 测试 ...
 *   $injector->restore();
 */
final class ConfigInjector
{
    /** @var array<string,mixed> 被修改过的配置键及其原始值 */
    private array $original = [];

    private ReflectionProperty $configProp;

    private ReflectionProperty $flatCacheProp;

    public function __construct()
    {
        $this->configProp    = new ReflectionProperty(Config::class, 'config');
        $this->configProp->setAccessible(true);
        $this->flatCacheProp = new ReflectionProperty(Config::class, 'flatCache');
        $this->flatCacheProp->setAccessible(true);
    }

    /**
     * 注入一个顶层配置项（如 install）
     */
    public function set(string $key, mixed $value): void
    {
        if (!array_key_exists($key, $this->original)) {
            $config = $this->configProp->getValue();
            $this->original[$key] = $config[$key] ?? null;
        }

        $config = $this->configProp->getValue();
        if (!is_array($config)) {
            $config = [];
        }
        $config[$key] = $value;
        $this->configProp->setValue($config);

        // 注入后清空扁平缓存，避免 config() 命中注入前的旧值
        $this->flatCacheProp->setValue([]);
    }

    /**
     * 还原本次注入过的所有配置项
     */
    public function restore(): void
    {
        if ($this->original === []) {
            return;
        }

        $config = $this->configProp->getValue();
        if (!is_array($config)) {
            $config = [];
        }
        foreach ($this->original as $key => $value) {
            if ($value === null) {
                unset($config[$key]);
            } else {
                $config[$key] = $value;
            }
        }
        $this->configProp->setValue($config);
        $this->flatCacheProp->setValue([]);
        $this->original = [];
    }
}
