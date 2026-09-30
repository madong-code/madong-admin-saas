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
 * 后端场景生成器
 * 负责生成后端文件路径
 */
class BackendSceneGenerator implements SceneGeneratorInterface
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
     * 构造函数
     *
     * @param array $config 配置信息
     */
    public function __construct(array $config)
    {
        $this->config       = $config;
        $this->pathResolver = new PathResolver();
    }

    /**
     * 生成文件路径
     *
     * @param string $fileType  文件类型
     * @param string $extension 文件扩展名
     *
     * @return string 文件路径
     */
    public function generateFilePath(string $fileType, string $extension = 'php'): string
    {
        $template   = $this->config['template'] ?? 'app';
        $moduleName = $this->config['package_name'] ?? 'default';
        $className  = $this->config['class_name'] ?? 'DefaultModel';
        $isPlugin   = $template !== 'app';

        // 处理特殊文件类型
//        if ($fileType === 'request') {
//            // 请求需要生成两个文件：FormRequest 和 QueryRequest
//            $dtoBasePath = $this->getBasePathForFileType($fileType);
//            $formPath    = $this->pathResolver->generatePath($dtoBasePath, $moduleName, $className, 'request_form', $extension, $isPlugin);
//            $queryPath   = $this->pathResolver->generatePath($dtoBasePath, $moduleName, $className, 'request_query', $extension, $isPlugin);
//
//            // 返回表单请求路径，查询请求路径会在生成器中单独处理
//            return $formPath;
//        }

        $basePath = $this->getBasePathForFileType($fileType);
        $path     = $this->pathResolver->generatePath($basePath, $moduleName, $className, $fileType, $extension, $isPlugin);

        return $path;
    }

    /**
     * 获取基础路径
     *
     * @return string 基础路径
     */
    private function getBasePath(): string
    {
        $template = $this->config['template'] ?? 'app';

        if ($template === 'app') {
            return base_path() . DS . 'app';
        } else {
            $pluginName = $this->config['namespace'] ?? '';
            return base_path() . DS . 'plugin' . DS . $pluginName . DS . 'app';
        }
    }

    /**
     * 根据文件类型获取基础路径
     *
     * @param string $fileType 文件类型
     *
     * @return string 基础路径
     */
    private function getBasePathForFileType(string $fileType): string
    {
        $template = $this->config['template'] ?? 'app';

        // 插件/App 模式统一使用同样的文件类型分流逻辑
        $adminApiFileTypes = ['controller', 'validate', 'request_form', 'request_query', 'response', 'route'];

        if ($template !== 'app') {
            $pluginName = $this->config['namespace'] ?? '';
            $basePluginPath = base_path() . DS . 'plugin' . DS . $pluginName . DS . 'app';
            if (in_array($fileType, $adminApiFileTypes)) {
                return $basePluginPath . DS . 'adminapi';
            }
            return $basePluginPath;
        }

        // app 模式
        if ($fileType === 'migration') {
            return base_path() . DS . 'database';
        } elseif (in_array($fileType, $adminApiFileTypes)) {
            return base_path() . DS . 'app' . DS . 'adminapi';
        } else {
            return base_path() . DS . 'app';
        }
    }
}
