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
 * 类型映射公共 Trait
 * 提供字段类型转换的公共方法，消除 ResponseGenerator / RequestFormGenerator / RequestQueryGenerator 中的重复代码
 */
trait TypeMappingTrait
{
    /**
     * 获取 PHP 类型
     * @param string $columnType 数据库字段类型
     * @return string PHP 类型
     */
    private function getPhpType(string $columnType): string
    {
        $typeMap = [
            'int' => 'int',
            'integer' => 'int',
            'string' => 'string',
            'varchar' => 'string',
            'text' => 'string',
            'boolean' => 'bool',
            'bool' => 'bool',
            'float' => 'float',
            'double' => 'float',
            'array' => 'array',
            'json' => 'array',
        ];

        return $typeMap[strtolower($columnType)] ?? 'string';
    }

    /**
     * 获取 OpenAPI 类型
     * @param string $columnType 数据库字段类型
     * @return string OpenAPI 类型
     */
    private function getOpenApiType(string $columnType): string
    {
        $typeMap = [
            'int' => 'integer',
            'integer' => 'integer',
            'string' => 'string',
            'varchar' => 'string',
            'text' => 'string',
            'boolean' => 'boolean',
            'bool' => 'boolean',
            'float' => 'number',
            'double' => 'number',
            'array' => 'array',
            'json' => 'object',
        ];

        return $typeMap[strtolower($columnType)] ?? 'string';
    }

    /**
     * 获取示例值
     * @param string $columnType 数据库字段类型
     * @return string 示例值
     */
    private function getExampleValue(string $columnType): string
    {
        $exampleMap = [
            'int' => '1',
            'integer' => '1',
            'string' => '示例值',
            'varchar' => '示例值',
            'text' => '示例文本',
            'boolean' => 'true',
            'bool' => 'true',
            'float' => '1.0',
            'double' => '1.0',
            'array' => '[]',
            'json' => '{"key": "value"}',
        ];

        return $exampleMap[strtolower($columnType)] ?? '示例值';
    }

    /**
     * 生成验证规则
     * @param array $column 字段信息
     * @return string 验证规则
     */
    private function generateValidationRules(array $column): string
    {
        $isRequired = isset($column['is_required']) && $column['is_required'];
        $columnType = $column['column_type'] ?? 'string';

        $rules = [];

        // 必填规则
        if ($isRequired) {
            $rules[] = 'required';
        } else {
            $rules[] = 'nullable';
        }

        // 类型规则
        switch (strtolower($columnType)) {
            case 'int':
            case 'integer':
                $rules[] = 'integer';
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
            default:
                $rules[] = 'string';
                break;
        }

        return implode('|', $rules);
    }
}
