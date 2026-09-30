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
namespace core\business\generator\factory;

use core\business\generator\interfaces\SceneGeneratorInterface;
use core\business\generator\interfaces\FileGeneratorInterface;
use core\business\generator\utils\TemplateRenderer;

/**
 * 生成器工厂
 * 负责创建不同类型的生成器，支持 DI 注入 TemplateRenderer
 * 场景生成器的映射通过配置 driven
 */
class GeneratorFactory
{
    /**
     * @var TemplateRenderer|null
     */
    private ?TemplateRenderer $templateRenderer = null;

    /**
     * 设置模板渲染器（单例注入）
     * @param TemplateRenderer $renderer
     */
    public function setTemplateRenderer(TemplateRenderer $renderer): void
    {
        $this->templateRenderer = $renderer;
    }

    /**
     * 创建场景生成器
     * 根据 scene_type 在配置中找到对应的 generator 类
     * - template_type = 'mono'   → FrontendSceneGenerator (传入 sceneType)
     * - template_type = '' (后端) → {SceneType}SceneGenerator
     *
     * @param string $sceneType 场景类型
     * @param array  $config    配置信息（含 _scene_type_defs）
     *
     * @return SceneGeneratorInterface 场景生成器
     * @throws \Exception
     */
    public function createSceneGenerator(string $sceneType, array $config): SceneGeneratorInterface
    {
        // 从 config 数组读取场景定义（由 ConfigParser 注入 _scene_type_defs）
        $sceneTypeDefs = $config['_scene_type_defs'] ?? [];
        $sceneDef = $sceneTypeDefs[$sceneType] ?? null;

        if (!$sceneDef) {
            throw new \Exception("Scene type not found in config: {$sceneType}");
        }

        $templateType = $sceneDef['template_type'] ?? '';

        if ($templateType !== '') {
            // 前端场景（非空 template_type = 前端）：统一使用 FrontendSceneGenerator
            $className = "core\\business\\generator\\scene\\FrontendSceneGenerator";
            if (!class_exists($className)) {
                throw new \Exception('Scene generator not found: ' . $className);
            }
            return new $className($config, $sceneType);
        }

        // 后端场景（template_type = ''）：{SceneType}SceneGenerator
        $className = "core\\business\\generator\\scene\\" . ucfirst($sceneType) . "SceneGenerator";
        if (!class_exists($className)) {
            throw new \Exception('Scene generator not found: ' . $sceneType);
        }
        return new $className($config);
    }

    /**
     * 创建文件类型生成器
     *
     * @param string $fileType 文件类型
     * @param array  $config   配置信息
     *
     * @return FileGeneratorInterface 文件类型生成器
     */
    public function createFileGenerator(string $fileType, array $config): FileGeneratorInterface
    {
        $fileTypeMap = [
            'api_model'     => 'ApiModel',
            'view_schema'   => 'ViewSchema',
            'request_form'  => 'RequestForm',
            'request_query' => 'RequestQuery',
            'migration'     => 'Migration',
            'route'         => 'Route',
        ];

        if (isset($fileTypeMap[$fileType])) {
            $className = "core\\business\\generator\\file\\" . $fileTypeMap[$fileType] . "Generator";
        } else {
            $className = "core\\business\\generator\\file\\" . ucfirst($fileType) . "Generator";
        }

        if (!class_exists($className)) {
            throw new \Exception('File generator not found: ' . $fileType);
        }

        // DI 注入：传入 templateRenderer
        return new $className($config, $this->templateRenderer);
    }
}
