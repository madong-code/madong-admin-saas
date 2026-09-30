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
 * API 模型生成器
 * 负责生成前端 API 模型文件内容
 */
class ApiModelGenerator extends AbstractFileGenerator
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
        
        // 生成字段类型映射
        $fields = '';
        if (isset($this->config['columns']) && is_array($this->config['columns'])) {
            foreach ($this->config['columns'] as $column) {
                if (isset($column['column_name']) && isset($column['column_type'])) {
                    $fieldName = $column['column_name'];
                    $fieldType = $this->getColumnType($column['column_type']);
                    $fieldComment = isset($column['column_comment']) ? $column['column_comment'] : '';
                    
                    // 如果有注释，添加注释行
                    if (!empty($fieldComment)) {
                        $fields .= "  /** {$fieldComment} */\n";
                    }
                    
                    $fields .= "  {$fieldName}: {$fieldType};\n";
                }
            }
        }
        
        $content = $this->templateRenderer->render('admin/api/types.stub', [
            'class_name' => $className,
            'module_name' => $moduleName,
            'row_type_name' => $rowTypeName,
            'fields' => $fields,
        ]);
        
        return $content;
    }
    
    /**
     * 根据数据库字段类型获取 TypeScript 类型
     * @param string $columnType 数据库字段类型
     * @return string TypeScript 类型
     */
    private function getColumnType(string $columnType): string
    {
        $typeMap = [
            'int' => 'number',
            'integer' => 'number',
            'tinyint' => 'number',
            'smallint' => 'number',
            'mediumint' => 'number',
            'bigint' => 'number',
            'float' => 'number',
            'double' => 'number',
            'decimal' => 'number',
            'string' => 'string',
            'varchar' => 'string',
            'char' => 'string',
            'text' => 'string',
            'mediumtext' => 'string',
            'longtext' => 'string',
            'date' => 'string',
            'datetime' => 'string',
            'timestamp' => 'string',
            'time' => 'string',
            'year' => 'number',
            'boolean' => 'boolean',
            'bool' => 'boolean',
            'json' => 'any',
            'array' => 'any[]',
        ];
        
        // 转换为小写并获取基础类型
        $columnType = strtolower($columnType);
        foreach ($typeMap as $key => $value) {
            if (strpos($columnType, $key) === 0) {
                return $value;
            }
        }
        
        // 默认类型
        return 'any';
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