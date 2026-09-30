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
 * API 生成器
 * 负责生成前端 API 文件内容
 */
class ApiGenerator extends AbstractFileGenerator
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
        $className = $this->config['class_name'] ?? 'DefaultModel';
        $moduleName = $this->config['package_name'] ?? 'default';
        
        // 生成带类名的接口类型名称
        $rowTypeName = $className . 'Row';
        
        // 生成baseUrl，使用连字符（-）而不是下划线（_）
        $basePath = strtolower(str_replace('_', '-', $moduleName));
        $classPath = strtolower(str_replace('_', '-', $className));
        $baseUrl = $this->config['base_url'] ?? '/' . $basePath . '/' . $classPath;
        
        $content = $this->templateRenderer->render('admin/api/index.stub', [
            'class_name' => $className,
            'module_name' => $moduleName,
            'base_url' => $baseUrl,
            'row_type_name' => $rowTypeName,
        ]);
        
        return $content;
    }

    /**
     * 获取文件扩展名
     * @return string 文件扩展名
     */
    public function getFileExtension(): string
    {
        return 'ts';
    }
}