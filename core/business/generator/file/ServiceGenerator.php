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
 * 服务生成器
 * 负责生成服务文件内容
 */
class ServiceGenerator extends AbstractFileGenerator
{
    public function __construct(array $config, ?TemplateRenderer $templateRenderer = null)
    {
        parent::__construct($config, $templateRenderer);
    }

    /**
     * 生成文件内容
     * @return string 文件内容
     */
    public function generateContent(): string
    {
        $template = $this->config['template'] ?? 'app';
        $isPlugin = $template !== 'app';
        $pluginName = $this->config['namespace'] ?? '';
        
        // 生成命名空间
        $packageName = $this->config['package_name'] ?? 'default';
        if ($isPlugin) {
            // 插件模式：plugin\{plugin_name}\app\service\{package_name}
            $namespace = "plugin\\{$pluginName}\\app\\service\\{$packageName}";
        } else {
            // App 模式：app\service\admin\{package_name}
            $namespace = "app\\service\\admin\\{$packageName}";
        }
        
        // 生成 DAO 命名空间
        if ($isPlugin) {
            $daoNamespace = "plugin\\{$pluginName}\\app\\dao\\{$packageName}";
        } else {
            $daoNamespace = "app\\dao\\{$packageName}";
        }
        
        $data = [
            'class_name' => $this->config['class_name'] ?? 'DefaultModel',
            'package_name' => $this->config['package_name'] ?? 'default',
            'table_content' => $this->config['table_content'] ?? '默认模型',
            'camel_class_name' => lcfirst($this->config['class_name'] ?? 'DefaultModel'),
            'namespace' => $namespace,
            'dao_namespace' => $daoNamespace,
        ];
        
        return $this->templateRenderer->render('server/service/service.stub', $data);
    }

    /**
     * 获取文件扩展名
     * @return string 文件扩展名
     */
    public function getFileExtension(): string
    {
        return 'php';
    }
}
