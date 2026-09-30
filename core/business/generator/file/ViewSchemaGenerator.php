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
 * 视图 Schema 生成器
 * 负责生成前端视图 Schema 文件内容
 */
class ViewSchemaGenerator extends AbstractFileGenerator
{
    public function __construct(array $config, ?TemplateRenderer $templateRenderer = null)
    {
        parent::__construct($config, $templateRenderer);
    }

    public function generateContent(): string
    {
        $className = $this->config['class_name'] ?? 'DefaultModel';
        $moduleName = $this->config['package_name'] ?? 'default';
        $dummyClassName = strtolower(preg_replace('/([a-z0-9])([A-Z])/', '$1_$2', $className));
        $serviceName = $className . 'Service';
        $kebabModuleName = str_replace('_', '-', $moduleName);
        $columns = $this->config['columns'] ?? [];
        $template = $this->config['template'] ?? 'app';
        $isPlugin = $template !== 'app';
        $pluginName = $this->config['namespace'] ?? '';

        // 生成 API import 路径
        if ($isPlugin) {
            // 插件模式: @/apps/{plugin}/api/{kebab_module_name}
            $apiImportPath = '@/apps/' . $pluginName . '/api/' . $kebabModuleName;
        } else {
            // 应用模式: @/api/{kebab_module_name}
            $apiImportPath = '@/api/' . $kebabModuleName;
        }

        $langKey = $this->generateLangKey($moduleName, $className);
        $langImportPath = $this->generateLangImportPath($moduleName);

        $tableColumns = $this->processTableColumns($columns, $langKey);
        $searchFormSchema = $this->processSearchFormSchema($columns, $langKey);
        $formSchema = $this->processFormSchema($columns, $langKey);

        $dialogType = '';
        if (isset($this->config['config']['edit_type']) && $this->config['config']['edit_type'] == 2) {
            $dialogType = 'dialogType: "drawer"';
        }

        $dialogWidth = $this->config['config']['dialog_width'] ?? '50%';

        $content = $this->templateRenderer->render('admin/views/schemas/index.stub', [
            'class_name' => $className,
            'module_name' => $moduleName,
            'dummy_class_name' => $dummyClassName,
            'service_name' => $serviceName,
            'kebab_module_name' => $kebabModuleName,
            'api_import_path' => $apiImportPath,
            'lang_key' => $langKey,
            'lang_import_path' => $langImportPath,
            'table_columns' => $tableColumns,
            'search_form_schema' => $searchFormSchema,
            'form_schema' => $formSchema,
            'use_crud' => isset($this->config['config']['use_crud']) ? (bool)$this->config['config']['use_crud'] : true,
            'has_add' => isset($this->config['config']['has_add']) ? (bool)$this->config['config']['has_add'] : true,
            'has_remove' => isset($this->config['config']['has_remove']) ? (bool)$this->config['config']['has_remove'] : true,
            'has_edit' => isset($this->config['config']['has_edit']) ? (bool)$this->config['config']['has_edit'] : true,
            'has_view' => isset($this->config['config']['has_view']) ? (bool)$this->config['config']['has_view'] : false,
            'dialog_type' => $dialogType,
            'dialog_width' => $dialogWidth,
        ]);

        return $content;
    }

    private function generateLangKey(string $moduleName, string $className): string
    {
        $kebabModuleName = strtolower(str_replace('_', '-', $moduleName));
        // 插件模式加插件名作为前缀，如 demo.sys-admin-dept
        $template = $this->config['template'] ?? 'app';
        $isPlugin = $template !== 'app';
        if ($isPlugin) {
            $pluginName = $this->config['namespace'] ?? '';
            if ($pluginName) {
                return $pluginName . '.' . $kebabModuleName;
            }
        }
        return $kebabModuleName;
    }

    private function generateLangImportPath(string $moduleName): string
    {
        return "@/lang/{$moduleName}";
    }

    private function processTableColumns(array $columns, string $langKey): string
    {
        $result = [];
        foreach ($columns as $column) {
            if (isset($column['is_lists']) && $column['is_lists'] == 1) {
                $tableItem = [
                    'prop' => $column['column_name'],
                    'label' => '$t(\'' . $langKey . '.table.columns.' . $column['column_name'] . '\')'
                ];
                if(isset($column['view_type']) && $column['view_type'] == 'ApiDict') {
                    $tableItem['component'] = 'ApiDict';
                    $tableItem['componentProps'] = [
                        'code' => $column['dict_type'],
                    ];
                }

                $result[] = '      ' . $this->arrayToCode($tableItem);
            }
        }
        return implode(",\n", $result);
    }

    private function arrayToCode(array $array): string
    {
        $items = [];
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $items[] = $key . ': ' . $this->arrayToCode($value);
            } elseif (is_bool($value)) {
                $items[] = $key . ': ' . ($value ? 'true' : 'false');
            } elseif (is_int($value) || is_float($value)) {
                $items[] = $key . ': ' . $value;
            } elseif (strpos($value, '$t(') === 0 || strpos($value, '[') === 0) {
                $items[] = $key . ': ' . $value;
            } else {
                $items[] = $key . ': "' . $value . '"';
            }
        }
        return '{' . implode(', ', $items) . '}';
    }

    private function processSearchFormSchema(array $columns, string $langKey): string
    {
        $result = [];
        foreach ($columns as $column) {
            if (isset($column['is_search']) && $column['is_search'] == 1) {
                $prop = $column['column_name'];
                if (strpos(strtoupper($prop), 'LIKE_') === 0) {
                    $prop = substr($prop, 5);
                } elseif (strpos(strtoupper($prop), 'EQ_') === 0) {
                    $prop = substr($prop, 3);
                }

                $searchItem = [
                    'component' => $this->getComponent($column),
                    'prop' => $prop,
                    'label' => '$t(\'' . $langKey . '.table.search.' . $prop . '\')',
                    'componentProps' => [
                        'placeholder' => '$t(\'' . $langKey . '.table.search.' . $prop . '\')'
                    ],
                    'colSpan' => 6
                ];

                if (isset($column['label_key']) && $column['label_key']) {
                    $searchItem['labelKey'] = $column['label_key'];
                }
                if (isset($column['value_key']) && $column['value_key']) {
                    $searchItem['valueKey'] = $column['value_key'];
                }

                if (isset($column['api']) && $column['api']) {
                    $searchItem['componentProps']['api'] = $column['api'];
                }

                if (isset($column['dict_type']) && $column['dict_type']) {
                    $searchItem['componentProps']['code'] = $column['dict_type'];
                }

                $result[] = '        ' . $this->arrayToCode($searchItem);
            }
        }
        return implode(",\n", $result);
    }

    private function processFormSchema(array $columns, string $langKey): string
    {
        $result = [];
        foreach ($columns as $column) {
            if (isset($column['is_pk']) && $column['is_pk'] == 1) {
                $item = [
                    'label' => '$t(\'' . $langKey . '.form.' . $column['column_name'] . '\')',
                    'prop' => $column['column_name'],
                    'component' => $this->getComponent($column),
                    'colSpan' => 24,
                    'show' => false
                ];
                $result[] = '        ' . $this->arrayToCode($item);
                continue;
            }

            if ((isset($column['is_insert']) && $column['is_insert'] == 1) || (isset($column['is_update']) && $column['is_update'] == 1)) {
                $item = [
                    'label' => '$t(\'' . $langKey . '.form.' . $column['column_name'] . '\')',
                    'prop' => $column['column_name'],
                    'component' => $this->getComponent($column),
                    'colSpan' => 24
                ];

                if (isset($column['show']) && $column['show'] == 1) {
                    $item['show'] = true;
                }

                if (isset($column['ifDetail']) && $column['ifDetail'] == 1) {
                    $item['ifDetail'] = true;
                }

                if (isset($column['validate_type']) && $column['validate_type']) {
                    $item['rules'] = $this->getValidationRules($column['validate_type']);
                }

                if (isset($column['label_key']) && $column['label_key']) {
                    $item['labelKey'] = $column['label_key'];
                }
                if (isset($column['value_key']) && $column['value_key']) {
                    $item['valueKey'] = $column['value_key'];
                }

                if (isset($column['api']) && $column['api']) {
                    $item['componentProps'] = [
                        'api' => $column['api']
                    ];
                }

                if (isset($column['dict_type']) && $column['dict_type']) {
                    if (!isset($item['componentProps'])) {
                        $item['componentProps'] = [];
                    }
                    $item['componentProps']['code'] = $column['dict_type'];
                }

                $result[] = '        ' . $this->arrayToCode($item);
            }
        }
        return implode(",\n", $result);
    }

    private function getComponent(array $column): string
    {
        if (isset($column['dict_type']) && $column['dict_type']) {
            return 'ApiDict';
        }

        if (isset($column['view_type']) && $column['view_type']) {
            $viewType = $column['view_type'];
            return $viewType;
        }
        return 'Input';
    }

    private function getValidationRules(string $validateType): string
    {
        $rules = [];
        $validateTypes = explode(',', $validateType);
        foreach ($validateTypes as $type) {
            switch (trim($type)) {
                case 'required':
                    $rules[] = '{"required":true,"message":"此项为必填项","trigger":"blur"}';
                    break;
                case 'email':
                    $rules[] = '{"type":"email","message":"请输入有效的邮箱地址","trigger":"blur"}';
                    break;
                case 'url':
                    $rules[] = '{"type":"url","message":"请输入有效的URL地址","trigger":"blur"}';
                    break;
                case 'number':
                    $rules[] = '{"type":"number","message":"请输入数字","trigger":"blur"}';
                    break;
            }
        }
        return '[' . implode(',', $rules) . ']';
    }

    public function getFileExtension(): string
    {
        return 'tsx';
    }
}
