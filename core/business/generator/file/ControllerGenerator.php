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
namespace core\business\generator\file;

use core\business\generator\utils\TemplateRenderer;

/**
 * 控制器生成器
 * 负责生成控制器文件内容
 */
class ControllerGenerator extends AbstractFileGenerator
{
    public function __construct(array $config, ?TemplateRenderer $templateRenderer = null)
    {
        parent::__construct($config, $templateRenderer);
    }

    /**
     * 生成文件内容
     *
     * @return string 文件内容
     */
    public function generateContent(): string
    {
        $template    = $this->config['template'] ?? 'app';
        $isPlugin    = $template !== 'app';
        $pluginName  = $this->config['namespace'] ?? '';
        $className   = $this->config['class_name'] ?? 'DefaultModel';
        $packageName = $this->config['package_name'] ?? 'default';

        // 生成连字符格式的名称（用于路由path）
        $packageNameDash = str_replace('_', '-', $packageName);
        $classNameDash   = str_replace('_', '-', strtolower($className));

        // 生成小写下划线格式的名称（用于权限码）
        $packageNameLower = strtolower($packageName);
        $classNameLower   = strtolower(str_replace('_', '', $className));

        // 生成命名空间
        if ($isPlugin) {
            // 插件模式：adminapi/ 下的对齐主项目，service/dao/model 共用
            $namespace               = "plugin\\{$pluginName}\\app\\adminapi\\controller\\{$packageName}";
            $schemaRequestNamespace  = "plugin\\{$pluginName}\\app\\adminapi\\schema\\request\\{$packageName}";
            $validateNamespace       = "plugin\\{$pluginName}\\app\\adminapi\\validate\\{$packageName}";
            $schemaResponseNamespace = "plugin\\{$pluginName}\\app\\adminapi\\schema\\response\\{$packageName}";
            $serviceNamespace        = "plugin\\{$pluginName}\\app\\service\\{$packageName}";
        } else {
            // App 模式
            $namespace               = "app\\adminapi\\controller\\{$packageName}";
            $schemaRequestNamespace  = "app\\adminapi\\schema\\request\\{$packageName}";
            $validateNamespace       = "app\\adminapi\\validate\\{$packageName}";
            $schemaResponseNamespace = "app\\adminapi\\schema\\response\\{$packageName}";
            $serviceNamespace        = "app\\service\\admin\\{$packageName}";
        }

        $data = [
            'class_name'                => $className,
            'package_name'              => $packageName,
            'package_name_dash'         => $packageNameDash,
            'class_name_dash'           => $classNameDash,
            'package_name_lower'        => $packageNameLower,
            'class_name_lower'          => $classNameLower,
            'table_content'             => $this->config['table_content'] ?? '默认模型',
            'camel_class_name'          => lcfirst($className),
            'namespace'                 => $namespace,
            'schema_request_namespace'  => $schemaRequestNamespace,
            'validate_namespace'        => $validateNamespace,
            'schema_response_namespace' => $schemaResponseNamespace,
            'service_namespace'         => $serviceNamespace,
        ];

        return $this->templateRenderer->render('server/controller/controller.stub', $data);
    }

    /**
     * 获取文件扩展名
     *
     * @return string 文件扩展名
     */
    public function getFileExtension(): string
    {
        return 'php';
    }
}
