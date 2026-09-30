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
 * Official Website: https://madong.tech
 */

namespace app\bootstrap;

use Webman\Bootstrap;
use Webman\Config;

/**
 * 配置加载器
 *
 * @author Mr.April
 * @since  1.0
 */
class CoreConfigBootstrap implements Bootstrap
{
    public static function start($worker): void
    {
        // 加载所有模块的配置
        $modulesPath = base_path('core');
        if (is_dir($modulesPath)) {
            $modules = scandir($modulesPath);
            foreach ($modules as $module) {
                if ($module === '.' || $module === '..' || !is_dir($modulesPath . DIRECTORY_SEPARATOR . $module)) {
                    continue;
                }

                // 检查模块是否启用
                $appConfigFile = $modulesPath . DIRECTORY_SEPARATOR . $module . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'app.php';
                if (!file_exists($appConfigFile)) {
                    continue;
                }
                
                $appConfig = include $appConfigFile;
                if (empty($appConfig['enable'])) {
                    continue;
                }

                // 加载模块配置
                $configDir = $modulesPath . DIRECTORY_SEPARATOR . $module . DIRECTORY_SEPARATOR . 'config';
                if (is_dir($configDir)) {
                    Config::load($configDir, ['route'], "core.$module");
                }
            }
        }
    }
}
