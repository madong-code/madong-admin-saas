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
namespace app\service\core\plugin;

use core\business\plugin\PluginPath;
use core\foundation\base\BaseService;

/**
 * 插件基类服务
 *
 * 提供基础路径属性和插件方法执行能力
 * CLI 和在线模式共享同一套逻辑
 */
abstract class PluginBaseService extends BaseService
{
    /**
     * 插件临时目录基础路径
     */
    const RUNTIME_PLUGIN_PATH = 'install/plugin';

    /**
     * 获取插件构建目录
     */
    public static function getPluginBuildPath(): string
    {
        return self::RUNTIME_PLUGIN_PATH . '/build';
    }

    /**
     * 获取插件包输出目录
     */
    public static function getPluginPackagesPath(): string
    {
        return self::RUNTIME_PLUGIN_PATH . '/packages';
    }

    /**
     * 获取插件终端输出目录
     */
    public static function getPluginTerminalPath(): string
    {
        return self::RUNTIME_PLUGIN_PATH . '/terminal';
    }

    /**
     * 插件根目录
     */
    protected string $plugin_path;

    public function __construct()
    {
        $this->plugin_path = base_path('plugin');
    }

    /**
     * 获取前端基础目录
     * 前端代码统一在 frontend 目录下
     *
     * @return string 前端基础目录
     */
    public function getFrontendPath(): string
    {
        return PluginPath::frontendPath();
    }

    /**
     * 获取前端指定项目目录
     *
     * @param string $frontendType 前端类型（admin/web/uni-app）
     * @return string 前端项目目录
     */
    public function getFrontendProjectPath(string $frontendType): string
    {
        return PluginPath::frontendProjectPath($frontendType);
    }

    /**
     * 获取插件配置（兼容 ConfigTrait）
     *
     * @param string $code 插件编码
     * @return array|null
     */
    protected function getPluginConfig(string $code): ?array
    {
        $configFile = $this->plugin_path . DIRECTORY_SEPARATOR . $code . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'info.php';
        if (is_file($configFile)) {
            return include $configFile;
        }
        return null;
    }

    /**
     * 执行插件 Install 类方法
     * 
     * 统一调用插件的 install/uninstall/update 方法
     * CLI 和在线模式都通过此方法调用
     *
     * @param string $code 插件编码
     * @param string $action 方法名 (install/uninstall/update)
     * @param string|null $version 版本（对于 install 是目标版本，对于 update 是新版本）
     * @param string|null $oldVersion 旧版本（仅 update 时使用）
     * @param bool $silent 是否静默执行失败（true: 失败不抛出异常, false: 失败抛出异常）
     * @param string|null $context 安装上下文 platform|tenant
     * @param string|int|null $tenantId 目标租户ID（tenant 上下文必传）
     * @param string|null $connection 目标连接名（库隔离租户必传，避免落到主库）
     * @return bool 是否成功执行
     * @throws \Exception 当 silent=false 且执行失败时抛出异常
     */
    protected function executePluginMethod(
        string $code,
        string $action,
        ?string $version = null,
        ?string $oldVersion = null,
        bool $silent = false,
        ?string $context = null,
        string|int|null $tenantId = null,
        ?string $connection = null
    ): bool {
        $className = "plugin\\" . $code . "\\Install";
        if (!class_exists($className)) {
            // 插件未提供 Install 类，静默跳过
            return true;
        }

        try {
            $pluginConfig = $this->getPluginConfig($code);
            $version = $pluginConfig['version'] ?? ($version ?? '1.0.0');

            // 实例化插件 Install 类
            $installInstance = new $className();

            // 显式透传上下文：批量/队列/平台远程场景下请求上下文可能没有租户信息
            if ($context !== null && method_exists($installInstance, 'setContext')) {
                $installInstance->setContext($context);
            }
            if ($tenantId !== null && method_exists($installInstance, 'setTenantId')) {
                $installInstance->setTenantId($tenantId);
            }
            if ($connection !== null && method_exists($installInstance, 'setConnection')) {
                $installInstance->setConnection($connection);
            }
            
            switch ($action) {
                case 'install':
                    if (method_exists($installInstance, 'install')) {
                        $installInstance->install($version);
                    }
                    break;
                    
                case 'uninstall':
                    if (method_exists($installInstance, 'uninstall')) {
                        $installInstance->uninstall($version);
                    }
                    break;
                    
                case 'update':
                    if (method_exists($installInstance, 'update')) {
                        $installInstance->update($oldVersion ?? '1.0.0', $version);
                    }
                    break;
                    
                default:
                    return false;
            }
            
            return true;
        } catch (\Exception $e) {
            // 插件方法执行失败，记录日志
            $logContext = [
                'plugin'     => $code,
                'action'     => $action,
                'version'    => $version,
                'old_version' => $oldVersion,
                'exception'  => $e->getMessage(),
                'file'       => $e->getFile(),
                'line'       => $e->getLine(),
            ];
            if ($silent) {
                \support\Log::warning('插件方法执行失败', $logContext);
            } else {
                \support\Log::error('插件方法执行失败', $logContext);
            }
            
            // 静默模式不抛出异常，非静默模式抛出异常
            if (!$silent) {
                throw $e;
            }
            return false;
        }
    }

    /**
     * 调用插件 Install 类的方法（兼容 CLI 模式）
     * 
     * 此方法专门用于 CLI 命令直接调用插件方法
     * 与在线模式的区别：CLI 模式不需要 SSE 返回，直接执行
     *
     * @param string $code 插件编码
     * @param string $action 方法名 (install/uninstall/update)
     * @param string|null $version 版本
     * @param string|null $oldVersion 旧版本（仅 update 时使用）
     * @return bool 是否成功
     * @throws \Exception
     */
    public function callPluginInstallMethod(string $code, string $action, ?string $version = null, ?string $oldVersion = null): bool
    {
        return $this->executePluginMethod($code, $action, $version, $oldVersion, false);
    }

    /**
     * 在指定租户上下文中调用插件 Install 类的方法
     *
     * 平台远程分发 / 批量 / 队列场景没有请求级租户上下文，
     * 必须显式传入 tenantId 与 connection，否则迁移与数据会落到主库。
     */
    public function callPluginInstallMethodForTenant(
        string $code,
        string $action,
        string|int $tenantId,
        ?string $version = null,
        ?string $oldVersion = null,
        ?string $connection = null
    ): bool {
        return $this->executePluginMethod(
            $code,
            $action,
            $version,
            $oldVersion,
            false,
            'tenant',
            $tenantId,
            $connection
        );
    }
}
