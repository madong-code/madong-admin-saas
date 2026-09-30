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
 * 语言包生成器
 * 负责生成前端语言包文件内容
 */
class LangGenerator extends AbstractFileGenerator
{
    public function __construct(array $config, ?TemplateRenderer $templateRenderer = null)
    {
        parent::__construct($config, $templateRenderer);
    }

    public function generateContent(): string
    {
        $className = $this->config['class_name'] ?? 'DefaultModel';
        $moduleName = $this->config['package_name'] ?? 'default';
        $columns = $this->config['columns'] ?? [];

        $langKey = $this->generateLangKey($moduleName, $className);
        $fields = $this->generateFieldsTranslations($columns, $langKey);
        $classComment = $this->getClassComment($columns);

        $content = $this->templateRenderer->render('admin/lang/zh-cn/index.stub', [
            'module_name' => $moduleName,
            'class_name' => $className,
            'lang_key' => $langKey,
            'class_name_comment' => $classComment,
            'fields' => $fields,
        ]);

        return $content;
    }

    private function generateLangKey(string $moduleName, string $className): string
    {
        $classKey = strtolower(preg_replace('/([a-z0-9])([A-Z])/', '$1_$2', $className));
        return "{$classKey}";
    }

    private function generateFieldsTranslations(array $columns, string $langKey): string
    {
        $tableColumnFields = [];
        $tableSearchFields = [];
        $tableSearchPlaceholders = [];
        $formFields = [];
        $formPlaceholders = [];
        
        foreach ($columns as $column) {
            $fieldName = $column['column_name'];
            $comment = $column['column_comment'] ?? $fieldName;
            
            if (isset($column['is_lists']) && $column['is_lists'] == 1) {
                $tableColumnFields[] = "      \"{$fieldName}\": \"{$comment}\"";
            }
            
            if ((isset($column['is_insert']) && $column['is_insert'] == 1) || 
                (isset($column['is_update']) && $column['is_update'] == 1)) {
                $formFields[] = "    \"{$fieldName}\": \"{$comment}\"";
                $formPlaceholders[] = "      \"{$fieldName}\": \"请{$comment}\"";
            }
            
            if (isset($column['is_search']) && $column['is_search'] == 1) {
                $tableSearchFields[] = "      \"{$fieldName}\": \"{$comment}\"";
                $tableSearchPlaceholders[] = "        \"{$fieldName}\": \"请{$comment}\"";
            }
        }
        
        $result = '';
        
        if (!empty($tableColumnFields) || !empty($tableSearchFields)) {
            $result .= "  \"table\": {\n";
            if (!empty($tableColumnFields)) {
                $result .= "    \"columns\": {\n" . implode(",\n", $tableColumnFields) . "\n    }";
            }
            if (!empty($tableSearchFields)) {
                if (!empty($tableColumnFields)) {
                    $result .= ",\n";
                }
                $result .= "    \"search\": {\n" . implode(",\n", $tableSearchFields);
                if (!empty($tableSearchPlaceholders)) {
                    $result .= ",\n      \"placeholder\": {\n" . implode(",\n", $tableSearchPlaceholders) . "\n      }";
                }
                $result .= "\n    }";
            }
            $result .= "\n  },\n";
        }
        
        if (!empty($formFields)) {
            $result .= "  \"form\": {\n" . implode(",\n", $formFields);
            if (!empty($formPlaceholders)) {
                $result .= ",\n    \"placeholder\": {\n" . implode(",\n", $formPlaceholders) . "\n    }";
            }
            $result .= "\n  }";
        }
        
        return rtrim($result, ",\n");
    }

    private function getClassComment(array $columns): string
    {
        return $this->config['class_name_comment'] ?? $this->config['table_comment'] ?? $this->config['class_name'];
    }

    public function getFileExtension(): string
    {
        return 'json';
    }
}
