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
namespace core\business\generator\utils;

/**
 * 配置解析器
 * 独立处理 GeneratorEngine 的配置解析逻辑，包括：
 * - basic.* 字段映射到顶层
 * - template / namespace / plugin_name 逻辑
 * - 驼峰化 class_name
 * - 双类型验证（template_type / scene_type，基于配置定义）
 * - 路径缺省值设置
 * - 向后兼容
 */
class ConfigParser
{
    /**
     * @var array 解析后的配置
     */
    private array $config;

    /**
     * @param array|object $rawConfig 原始配置（支持数组或 Model toArray）
     * @throws \Exception
     */
    public function __construct(array|object $rawConfig)
    {
        $this->config = $this->parse($rawConfig);
    }

    /**
     * 获取解析后的完整配置
     * @return array
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * 获取单个配置项
     * @param string $key
     * @param mixed  $default
     * @return mixed
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->config[$key] ?? $default;
    }

    /**
     * 加载 generator 系统配置
     * 优先从框架 config() 获取，否则直接加载文件
     * @return array
     */
    private static function loadSystemConfig(): array
    {
        $config = config('core.business.generator', []);
        if (!empty($config)) {
            return $config;
        }

        // Fallback: 直接加载文件
        $paths = [
            (defined('BASE_PATH') ? BASE_PATH : '') . DIRECTORY_SEPARATOR . 'core' . DIRECTORY_SEPARATOR . 'business' . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'generator.php',
            dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'generator.php',
        ];
        foreach ($paths as $path) {
            if (file_exists($path)) {
                return include $path;
            }
        }
        return [];
    }

    /**
     * 核心解析逻辑
     * @param array|object $rawConfig
     * @return array
     * @throws \Exception
     */
    private function parse(array|object $rawConfig): array
    {
        if (is_object($rawConfig)) {
            $parsedConfig = (array) $rawConfig;
        } else {
            $parsedConfig = $rawConfig;
        }

        // 加载系统配置定义
        $systemConfig = self::loadSystemConfig();
        $sceneTypeDefs = $systemConfig['scene_types'] ?? [];
        $templateTypeDefs = $systemConfig['template_types'] ?? [];

        // 注入到 config 中供其他组件使用
        $parsedConfig['_scene_type_defs'] = $sceneTypeDefs;
        $parsedConfig['_template_type_defs'] = $templateTypeDefs;

        // 处理 basic.* 字段映射到顶层
        // 优先级：module_name > table_name（module_name 是用户指定的模块目录名）
        if (isset($parsedConfig['basic']) && is_array($parsedConfig['basic'])) {
            $basic = $parsedConfig['basic'];
            if (isset($basic['module_name']) && !empty($basic['module_name'])) {
                $parsedConfig['package_name'] ??= $basic['module_name'];
            } elseif (isset($basic['table_name']) && !empty($basic['table_name'])) {
                $parsedConfig['package_name'] ??= $basic['table_name'];
            }
            if (isset($basic['class_name']) && !empty($basic['class_name'])) {
                $parsedConfig['class_name'] ??= $basic['class_name'];
            } elseif (isset($basic['table_name']) && !empty($basic['table_name'])) {
                $parsedConfig['class_name'] ??= $basic['table_name'];
            }
            if (isset($basic['table_comment']) && !empty($basic['table_comment'])) {
                $parsedConfig['table_comment'] ??= $basic['table_comment'];
            }
            if (isset($basic['class_name_comment']) && !empty($basic['class_name_comment'])) {
                $parsedConfig['class_name_comment'] ??= $basic['class_name_comment'];
            }
            if (isset($basic['plugin_name']) && !empty($basic['plugin_name'])) {
                $parsedConfig['plugin_name'] = $basic['plugin_name'];
                $parsedConfig['namespace'] = $basic['plugin_name'];
                $parsedConfig['template'] = $basic['plugin_name'];
            }
        }

        // 顶层 package_name 兜底：module_name > table_name
        if (!isset($parsedConfig['package_name'])) {
            if (!empty($parsedConfig['module_name'])) {
                $parsedConfig['package_name'] = $parsedConfig['module_name'];
            } elseif (!empty($parsedConfig['table_name'])) {
                $parsedConfig['package_name'] = $parsedConfig['table_name'];
            }
        }
        if (!isset($parsedConfig['class_name'])) {
            if (!empty($parsedConfig['table_name'])) {
                $parsedConfig['class_name'] = $parsedConfig['table_name'];
            }
        }

        // 处理 template / namespace / plugin_name
        $parsedConfig['template'] ??= 'app';
        if ($parsedConfig['template'] !== 'app') {
            $parsedConfig['namespace'] ??= $parsedConfig['template'];
        }

        // 驼峰化 class_name
        if (!empty($parsedConfig['class_name'])) {
            $parsedConfig['class_name'] = str_replace(' ', '', ucwords(str_replace('_', ' ', $parsedConfig['class_name'])));
        }

        // --- 确定 scene_type 是否为原始配置显式提供 ---
        $hasExplicitSceneType = array_key_exists('scene_type', $parsedConfig);

        // --- 路径相关 ---
        // template_root_dir
        $parsedConfig['template_root_dir'] ??= 'template';

        // template_type：基于配置定义验证
        $templateTypeStr = $parsedConfig['template_type'] ?? 'mono';
        if (!isset($templateTypeDefs[$templateTypeStr])) {
            throw new \Exception("无效的 template_type: '{$templateTypeStr}'，有效值: " . implode(', ', array_keys($templateTypeDefs)));
        }
        $parsedConfig['template_type'] = $templateTypeStr;

        // scene_type：基于配置定义验证（向后兼容）
        $sceneTypeStr = $parsedConfig['scene_type'] ?? 'backend';
        if (!isset($sceneTypeDefs[$sceneTypeStr])) {
            throw new \Exception("无效的 scene_type: '{$sceneTypeStr}'，有效值: " . implode(', ', array_keys($sceneTypeDefs)));
        }
        $parsedConfig['scene_type'] = $sceneTypeStr;

        // 检查 scene_type 是否属于 template_type 支持的场景集合
        $supportedScenes = $templateTypeDefs[$templateTypeStr]['supported_scenes'] ?? [];
        if (!in_array($sceneTypeStr, $supportedScenes, true)) {
            throw new \Exception("scene_type '{$sceneTypeStr}' 不受 template_type '{$templateTypeStr}' 支持，支持: " . implode(', ', $supportedScenes));
        }

        // template_sub_path：优先取场景配置默认值，config 可覆盖
        $sceneDef = $sceneTypeDefs[$sceneTypeStr] ?? [];
        $parsedConfig['template_sub_path'] ??= $sceneDef['default_sub_path'] ?? '';

        // --- scene_types 和 file_types_map 默认值 ---
        $defaultSceneTypes = $systemConfig['default_scene_types'] ?? ['backend', 'admin'];
        $fileTypesMapDefault = $systemConfig['file_types_map'] ?? [];

        if (!isset($parsedConfig['scene_types'])) {
            if (!$hasExplicitSceneType) {
                $parsedConfig['scene_types'] = $defaultSceneTypes;
            } else {
                $parsedConfig['scene_types'] = [$parsedConfig['scene_type']];
            }
        }
        if (!isset($parsedConfig['file_types_map'])) {
            $parsedConfig['file_types_map'] = !empty($fileTypesMapDefault)
                ? $fileTypesMapDefault
                : [
                    'backend' => ['controller', 'model', 'service', 'dao', 'validate', 'request_form', 'request_query', 'response', 'migration', 'route'],
                    'admin' => ['api', 'api_model', 'view', 'view_schema', 'lang'],
                    'platform' => ['api', 'api_model', 'view', 'view_schema', 'lang'],
                    'web' => ['api', 'api_model', 'view', 'view_schema', 'lang'],
                ];
        }

        // 验证必要参数
        $this->validateRequired($parsedConfig);

        return $parsedConfig;
    }

    /**
     * 验证必要参数
     * @param array $config
     * @throws \Exception
     */
    private function validateRequired(array $config): void
    {
        if (empty($config['package_name'])) {
            throw new \Exception('缺少必要参数: package_name (table_name)');
        }
        if (empty($config['class_name'])) {
            throw new \Exception('缺少必要参数: class_name');
        }
    }
}
