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
 * DAO 生成器
 * 负责生成 DAO 文件内容
 */
class DaoGenerator extends AbstractFileGenerator
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
            // 插件模式：plugin\{plugin_name}\app\dao\{package_name}
            $namespace = "plugin\\{$pluginName}\\app\\dao\\{$packageName}";
            $modelNamespace = "plugin\\{$pluginName}\\app\\model\\{$packageName}";
        } else {
            // App 模式：app\dao\{package_name}
            $namespace = "app\\dao\\{$packageName}";
            $modelNamespace = "app\\model\\{$packageName}";
        }
        
        $data = [
            'class_name' => $this->config['class_name'] ?? 'DefaultModel',
            'package_name' => $this->config['package_name'] ?? 'default',
            'table_name' => $this->config['table_name'] ?? 'default_table',
            'camel_class_name' => lcfirst($this->config['class_name'] ?? 'DefaultModel'),
            'namespace' => $namespace,
            'model_namespace' => $modelNamespace,
        ];
        
        return $this->templateRenderer->render('server/dao/dao.stub', $data);
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
