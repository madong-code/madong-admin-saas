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

use Illuminate\Database\Eloquent\Relations\Relation;
use Webman\Bootstrap;

/**
 * 多态关联映射 Bootstrap
 *
 * 功能：
 * 1. 自动加载主项目的 morph_map 配置
 * 2. 自动扫描并加载所有插件中的 morph_map 配置
 * 3. 合并所有映射并注册到 Laravel ORM
 *
 * 使用场景：
 * - 审核模块（Review、Audit）需要关联多个不同模型
 * - 评论、点赞、收藏等功能需要关联多种资源
 * - 插件系统需要支持多态关联
 *
 * @author Mr.April
 * @since  1.0
 */
class MorphMapBootstrap implements Bootstrap
{
    public static function start($worker): void
    {
        // Is it console environment?
        $is_console = !$worker;
        if ($is_console) {
            // If you do not want to execute this in console, just return.
            return;
        }

        // 收集所有多态映射
        $morphMap = self::collectMorphMap();

        // 注册映射到 Laravel ORM
        if (!empty($morphMap)) {
            Relation::enforceMorphMap($morphMap);
        }
    }

    /**
     * 强制加载 morphMap（用于 CLI 环境）
     * 
     * @return void
     */
    public static function forceLoad(): void
    {
        // 收集所有多态映射
        $morphMap = self::collectMorphMap();

        // 注册映射到 Laravel ORM
        if (!empty($morphMap)) {
            Relation::enforceMorphMap($morphMap);
        }
    }

    /**
     * 收集所有多态映射配置
     *
     * @return array
     */
    protected static function collectMorphMap(): array
    {
        $morphMap = [];

        // 1. 加载主项目的多态映射配置
        $mainConfig = config('morph_map.map', []);
        $morphMap = array_merge($morphMap, $mainConfig);

        // 2. 加载所有插件的多态映射配置
        $pluginConfigs = self::loadPluginMorphMaps();
        return array_merge($morphMap, $pluginConfigs);
    }

    /**
     * 加载所有插件的多态映射配置
     *
     * @return array
     */
    protected static function loadPluginMorphMaps(): array
    {
        $morphMap = [];

        // 获取插件目录路径
        $pluginPath = base_path() . '/plugin';

        if (!is_dir($pluginPath)) {
            return $morphMap;
        }

        // 遍历插件目录
        $plugins = scandir($pluginPath);
        foreach ($plugins as $plugin) {
            // 跳过系统目录
            if ($plugin === '.' || $plugin === '..') {
                continue;
            }

            // 检查插件配置文件
            $pluginConfigFile = "{$pluginPath}/{$plugin}/config/morph_map.php";
            if (is_file($pluginConfigFile)) {
                $pluginMorphMap = self::loadPluginMorphMap($pluginConfigFile);
                if (!empty($pluginMorphMap)) {
                    $morphMap = array_merge($morphMap, $pluginMorphMap);
                }
            }
        }

        return $morphMap;
    }

    /**
     * 加载单个插件的多态映射配置
     *
     * @param string $configFile
     * @return array
     */
    protected static function loadPluginMorphMap(string $configFile): array
    {
        $morphMap = [];

        if (!file_exists($configFile)) {
            return $morphMap;
        }

        $config = include $configFile;

        // 支持三种配置格式（与主项目 config/morph_map.php 对齐）：
        // 格式1：['map' => ['question' => 'plugin\...\Question']]  （推荐，与主配置一致）
        // 格式2：['morph_map' => ['question' => ...]]
        // 格式3：直接返回映射数组 ['question' => 'plugin\...\Question']
        if (is_array($config)) {
            if (isset($config['map']) && is_array($config['map'])) {
                $morphMap = $config['map'];
            } elseif (isset($config['morph_map']) && is_array($config['morph_map'])) {
                $morphMap = $config['morph_map'];
            } else {
                // 配置文件本身就是 alias => class 映射
                $morphMap = $config;
            }
        }

        return $morphMap;
    }
}

