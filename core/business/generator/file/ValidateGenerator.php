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
 * 验证器生成器
 * 负责生成验证器文件内容
 */
class ValidateGenerator extends AbstractFileGenerator
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
            // 插件模式：对齐主项目
            $namespace = "plugin\\{$pluginName}\\app\\adminapi\\validate\\{$packageName}";
        } else {
            // App 模式：app\adminapi\validate\{package_name}
            $namespace = "app\\adminapi\\validate\\{$packageName}";
        }
        
        $data = [
            'class_name' => $this->config['class_name'] ?? 'DefaultModel',
            'package_name' => $this->config['package_name'] ?? 'default',
            'columns' => $this->config['columns'] ?? [],
            'namespace' => $namespace,
        ];
        
        // 生成场景字段
        $storeFields = [];
        $updateFields = [];
        $rules = [];
        $messages = [];
        
        if (isset($data['columns']) && is_array($data['columns'])) {
            foreach ($data['columns'] as $column) {
                $columnName = $column['column_name'];
                $columnComment = $column['column_comment'];
                $isRequired = isset($column['is_required']) && $column['is_required'];
                $isInsert = isset($column['is_insert']) && $column['is_insert'];
                $isUpdate = isset($column['is_update']) && $column['is_update'];
                $columnType = $column['column_type'] ?? 'string';
                
                // 生成验证规则
                $validationRule = $this->generateValidationRule($column);
                if (!empty($validationRule)) {
                    $rules[] = "'{$columnName}' => '{$validationRule}'";
                    
                    // 生成错误信息
                    $errorMessages = $this->generateErrorMessage($column);
                    foreach ($errorMessages as $rule => $message) {
                        $messages[] = "'{$columnName}.{$rule}' => '{$message}'";
                    }
                    
                    // 添加到场景
                    $isPk = isset($column['is_pk']) && $column['is_pk'];
                    if ($isInsert && !$isPk) {
                        $storeFields[] = "'{$columnName}'";
                    }
                    if ($isUpdate) {
                        $updateFields[] = "'{$columnName}'";
                    }
                }
            }
        }
        
        // 添加到数据中
        $data['store_fields'] = implode(",\n            ", $storeFields);
        $data['update_fields'] = implode(",\n            ", $updateFields);
        $data['rules'] = implode(",\n            ", $rules);
        $data['messages'] = implode(",\n            ", $messages);
        
        return $this->templateRenderer->render('server/validate/validation.stub', $data);
    }

    /**
     * 生成验证规则
     * @param array $column 字段信息
     * @return string 验证规则
     */
    private function generateValidationRule(array $column): string
    {
        $isRequired = isset($column['is_required']) && $column['is_required'];
        $columnType = $column['column_type'] ?? 'string';
        $isPk = isset($column['is_pk']) && $column['is_pk'];
        
        $rules = [];
        
        // 必填规则
        if ($isRequired) {
            $rules[] = 'required';
        }
        
        // 类型规则
        switch (strtolower($columnType)) {
            case 'int':
            case 'integer':
                // 主键字段支持int和string两种类型（如雪花ID）
                if ($isPk) {
                    $rules[] = 'integer|string';
                } else {
                    $rules[] = 'integer';
                }
                break;
            case 'float':
            case 'double':
                $rules[] = 'numeric';
                break;
            case 'boolean':
            case 'bool':
                $rules[] = 'boolean';
                break;
            case 'array':
                $rules[] = 'array';
                break;
            case 'email':
                $rules[] = 'email';
                break;
            default:
                $rules[] = 'string';
                break;
        }
        
        return implode('|', $rules);
    }

    /**
     * 生成错误信息
     * @param array $column 字段信息
     * @return array 错误信息
     */
    private function generateErrorMessage(array $column): array
    {
        $columnName = $column['column_name'];
        $columnComment = $column['column_comment'];
        $isRequired = isset($column['is_required']) && $column['is_required'];
        $columnType = $column['column_type'] ?? 'string';
        
        $messages = [];
        
        // 必填错误信息
        if ($isRequired) {
            $messages['required'] = "{$columnComment}必须填写";
        }
        
        $isPk = isset($column['is_pk']) && $column['is_pk'];
        
        // 类型错误信息
        switch (strtolower($columnType)) {
            case 'int':
            case 'integer':
                // 主键字段支持int和string两种类型（如雪花ID）
                if ($isPk) {
                    $messages['integer'] = "{$columnComment}必须是整数或字符串";
                    $messages['string'] = "{$columnComment}必须是整数或字符串";
                } else {
                    $messages['integer'] = "{$columnComment}必须是整数";
                }
                break;
            case 'float':
            case 'double':
                $messages['numeric'] = "{$columnComment}必须是数字";
                break;
            case 'boolean':
            case 'bool':
                $messages['boolean'] = "{$columnComment}必须是布尔值";
                break;
            case 'array':
                $messages['array'] = "{$columnComment}必须是数组";
                break;
            case 'email':
                $messages['email'] = "{$columnComment}格式不正确";
                break;
            default:
                $messages['string'] = "{$columnComment}必须是字符串";
                break;
        }
        
        return $messages;
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
