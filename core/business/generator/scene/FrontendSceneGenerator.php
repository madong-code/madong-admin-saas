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
namespace core\business\generator\scene;

use core\business\generator\interfaces\SceneGeneratorInterface;
use core\business\generator\utils\PathResolver;

/**
 * 前端场景生成器
 * 负责生成前端文件路径
 * 子路径通过配置 driven, 无需依赖枚举
 */
class FrontendSceneGenerator implements SceneGeneratorInterface
{
    /**
     * @var array 配置信息
     */
    private array $config;

    /**
     * @var PathResolver 路径解析器
     */
    private PathResolver $pathResolver;

    /**
     * @var string 当前场景类型
     */
    private string $sceneType;

    /**
     * 构造函数
     * @param array  $config    配置信息（含 _scene_type_defs）
     * @param string $sceneType 当前场景类型 (admin/platform/web)
     */
    public function __construct(array $config, string $sceneType = 'admin')
    {
        $this->config = $config;
        $this->sceneType = $sceneType;
        $this->pathResolver = new PathResolver();
    }

    /**
     * 生成文件路径
     * @param string $fileType 文件类型
     * @param string $extension 文件扩展名
     * @return string 文件路径
     */
    public function generateFilePath(string $fileType, string $extension = 'php'): string
    {
        $moduleName = $this->config['package_name'] ?? 'default';
        $className = $this->config['class_name'] ?? 'DefaultModel';

        $basePath = $this->getBasePath();
        $path = $this->pathResolver->generatePath($basePath, $moduleName, $className, $fileType, $extension);

        return $path;
    }

    /**
     * 获取基础路径
     * 拼接公式: {template_root_dir}/{template_type}/{template_sub_path}
     * 子路径从 _scene_type_defs 中按当前 sceneType 读取
     * {plugin} 占位符在插件模式下替换为 plugin_name
     * @return string
     */
    private function getBasePath(): string
    {
        $templateRootDir = $this->config['template_root_dir'] ?? 'template';

        // 从 _scene_type_defs 读取当前场景的 template_type（即 template/ 下的目录名）和子路径
        // 如 admin → template_type='mono', default_sub_path='apps/admin' → template/mono/apps/admin/src
        // 如 web   → template_type='web',  default_sub_path=''           → template/web/src
        // 如 h5    → template_type='h5',   default_sub_path=''           → template/h5/src
        $sceneTypeDefs = $this->config['_scene_type_defs'] ?? [];
        $sceneDef = $sceneTypeDefs[$this->sceneType] ?? [];
        $templateType = $sceneDef['template_type'] ?? 'mono';

        // 插件模式使用 plugin_sub_path，app 模式使用 default_sub_path
        $isPlugin = $this->config['template'] !== 'app';
        if ($isPlugin) {
            $templateSubPath = $sceneDef['plugin_sub_path'] ?? $sceneDef['default_sub_path'] ?? 'admin/src/plugin/{plugin}';
        } else {
            $templateSubPath = $sceneDef['default_sub_path'] ?? '';
        }

        // 替换 {plugin} 占位符
        $subPath = $templateSubPath;
        if ($isPlugin) {
            $pluginName = $this->config['namespace'] ?? 'plugin';
            $subPath = str_replace('{plugin}', $pluginName, $subPath);
        }

        $projectRoot = dirname(base_path());
        $subPathSegment = $subPath !== '' ? DS . $subPath : '';
        // 插件模式的 plugin_sub_path 已包含 src（如 apps/admin/src/plugin/{plugin}），不再追加
        if ($isPlugin) {
            return $projectRoot . DS . $templateRootDir . DS . $templateType . $subPathSegment;
        }
        return $projectRoot . DS . $templateRootDir . DS . $templateType . $subPathSegment . DS . 'src';
    }
}
