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
 * Schema 生成器
 * 负责生成 Schema 文件内容
 */
class ResponseGenerator extends AbstractFileGenerator
{
    use \core\business\generator\utils\TypeMappingTrait;

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
            $namespace = "plugin\\{$pluginName}\\app\\adminapi\\schema\\response\\{$packageName}";
        } else {
            $namespace = "app\\adminapi\\schema\\response\\{$packageName}";
        }

        $data = [
            'class_name' => $this->config['class_name'] ?? 'DefaultModel',
            'package_name' => $this->config['package_name'] ?? 'default',
            'columns' => $this->config['columns'] ?? [],
            'namespace' => $namespace,
        ];

        // 生成字段注解
        $fields = '';
        if (isset($data['columns']) && is_array($data['columns'])) {
            foreach ($data['columns'] as $column) {
                if (isset($column['is_pk']) && $column['is_pk']) {
                    continue;
                }
                $fields .= $this->generateFieldAnnotation($column);
            }
        }

        $data['fields'] = $fields;

        return $this->templateRenderer->render('server/schema/response/schema.stub', $data);
    }

    /**
     * 生成字段注解
     * @param array $column 字段信息
     * @return string 字段注解代码
     */
    private function generateFieldAnnotation(array $column): string
    {
        $columnName = $column['column_name'];
        $columnComment = $column['column_comment'];
        $isRequired = isset($column['is_required']) && $column['is_required'];
        $columnType = $column['column_type'] ?? 'string';

        $phpType = $this->getPhpType($columnType);
        $nullableType = !$isRequired ? '?' . $phpType : $phpType;
        $defaultValue = !$isRequired ? ' = null' : '';

        $annotation = "    #[OA\Property(\n";
        $annotation .= "        description: '{$columnComment}',\n";
        $annotation .= "        type: '{$this->getOpenApiType($columnType)}',\n";
        $annotation .= "        example: '{$this->getExampleValue($columnType)}',\n";
        $annotation .= "        nullable: " . ($isRequired ? 'false' : 'true') . "\n";
        $annotation .= "    )]\n";

        $annotation .= "    public {$nullableType} $" . "{$columnName}{$defaultValue};\n\n";

        return $annotation;
    }
}
