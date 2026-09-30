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
namespace core\business\plugin;

/**
 * 插件路径管理中心
 * 统一管理所有插件相关的文件路径，消除各层之间的路径重复
 */
class PluginPath
{
    /**
     * 项目根目录
     * e.g. /var/www/madong-saas
     */
    public static function projectRoot(): string
    {
        return dirname(base_path());
    }

    /**
     * 前端仓库根目录
     * e.g. /var/www/madong-saas/frontend
     */
    public static function frontendPath(): string
    {
        return self::projectRoot() . '/frontend';
    }

    /**
     * 前端指定项目路径
     * e.g. frontend/admin, frontend/web
     */
    public static function frontendProjectPath(string $type): string
    {
        return self::frontendPath() . '/' . $type;
    }

    /**
     * 插件运行时根目录
     * e.g. backend/plugin/sms-verification
     */
    public static function pluginRoot(string $code): string
    {
        return base_path('plugin') . '/' . $code;
    }

    /**
     * 插件配置目录
     * e.g. backend/plugin/sms-verification/config
     */
    public static function pluginConfigPath(string $code): string
    {
        return self::pluginRoot($code) . '/config';
    }

    /**
     * 安装标记文件
     * e.g. backend/plugin/sms-verification/config/installed.php
     */
    public static function installedFlagPath(string $code): string
    {
        return self::pluginConfigPath($code) . '/installed.php';
    }

    /**
     * 模板项目路径（用于打包时引用模板结构）
     * e.g. projectRoot/template/mono/apps/admin
     */
    public static function templateProjectPath(string $type): string
    {
        return self::projectRoot() . '/template/' . $type;
    }

    /**
     * 运行时临时目录
     * e.g. backend/runtime/install/plugin
     */
    public static function runtimePluginPath(): string
    {
        return runtime_path('install/plugin');
    }

    /**
     * 项目配置文件
     * e.g. backend/config/madong.php
     */
    public static function configPath(string $name): string
    {
        return base_path('config') . '/' . $name;
    }
}
