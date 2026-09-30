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
 * 模型生成器
 * 负责生成模型文件内容
 */
class ModelGenerator extends AbstractFileGenerator
{
    public function __construct(array $config, ?TemplateRenderer $templateRenderer = null)
    {
        parent::__construct($config, $templateRenderer);
    }

    /**
     * 生成文件内容
     *
     * @return string 文件内容
     * @throws \Exception
     */
    public function generateContent(): string
    {
        $template = $this->config['template'] ?? 'app';
        $isPlugin = $template !== 'app';
        $pluginName = $this->config['namespace'] ?? '';
        
        // 生成命名空间
        $packageName = $this->config['package_name'] ?? 'default';
        if ($isPlugin) {
            // 插件模式：plugin\{plugin_name}\app\model\{package_name}
            $namespace = "plugin\\{$pluginName}\\app\\model\\{$packageName}";
        } else {
            // App 模式：app\model\{package_name}
            $namespace = "app\\model\\{$packageName}";
        }
        
        $data = [
            'class_name' => $this->config['class_name'] ?? 'DefaultModel',
            'package_name' => $this->config['package_name'] ?? 'default',
            'table_name' => $this->config['table_name'] ?? 'default_table',
            'table_content' => $this->config['table_content'] ?? '默认模型',
            'camel_class_name' => lcfirst($this->config['class_name'] ?? 'DefaultModel'),
            'namespace' => $namespace,
        ];
        
        // 查找主键字段
        $primaryKey = 'id';
        if (isset($this->config['columns']) && is_array($this->config['columns'])) {
            foreach ($this->config['columns'] as $column) {
                if (isset($column['column_name']) && isset($column['is_pk']) && $column['is_pk']) {
                    $primaryKey = $column['column_name'];
                    break;
                }
            }
        }
        
        // 添加主键字段到数据中
        $data['primary_key'] = $primaryKey;
        
        // 生成首字母大写的主键字段名，处理下划线的情况
        $ucfirstPrimaryKey = ucfirst($primaryKey);
        $ucfirstPrimaryKey = str_replace('_', '', ucwords($ucfirstPrimaryKey, '_'));
        $data['ucfirst_primary_key'] = $ucfirstPrimaryKey;
        
        // 生成可填充字段
        $fillableFields = '';
        if (isset($this->config['columns']) && is_array($this->config['columns'])) {
            foreach ($this->config['columns'] as $column) {
                if (isset($column['column_name'])) {
                    $fillableFields .= "        '{$column['column_name']}',\n";
                }
            }
        }
        
        // 添加到数据中
        $data['fillable_fields'] = $fillableFields;
        
        // 生成基于is_search字段的搜索器
        $searchers = '';
        if (isset($this->config['columns']) && is_array($this->config['columns'])) {
            foreach ($this->config['columns'] as $column) {
                if (isset($column['column_name']) && isset($column['is_search']) && $column['is_search']) {
                    $columnName = $column['column_name'];
                    // 生成首字母大写的字段名，处理下划线的情况
                    $ucfirstColumnName = ucfirst($columnName);
                    $ucfirstColumnName = str_replace('_', '', ucwords($ucfirstColumnName, '_'));
                    
                    // 确定搜索运算符
                    $operator = 'like';
                    if (isset($column['query_type']) && !empty($column['query_type'])) {
                        $operator = strtoupper($column['query_type']);
                    }
                    
                    // 生成搜索器方法
                    $searchers .= "    /**\n";
                    $searchers .= "     * 搜索器:{$columnName}\n";
                    $searchers .= "     */\n";
                    $searchers .= "    public function scope{$ucfirstColumnName}(\$query, \$value)\n";
                    $searchers .= "    {\n";
                    $searchers .= "        if (\$value) {\n";
                    
                    // 根据不同的运算符生成不同的搜索逻辑
                    switch ($operator) {
                        case '=':
                        case '!=':
                        case '>':
                        case '>=':
                        case '<':
                        case '<=':
                            $searchers .= "            \$query->where('{$columnName}', '{$operator}', \$value);\n";
                            break;
                        case 'LIKE':
                        case 'NOT LIKE':
                            $searchers .= "            \$query->where('{$columnName}', '{$operator}', '%' . \$value . '%');\n";
                            break;
                        case 'IN':
                            $searchers .= "            if (is_array(\$value)) {\n";
                            $searchers .= "                \$query->whereIn('{$columnName}', \$value);\n";
                            $searchers .= "            }\n";
                            break;
                        case 'NOT IN':
                            $searchers .= "            if (is_array(\$value)) {\n";
                            $searchers .= "                \$query->whereNotIn('{$columnName}', \$value);\n";
                            $searchers .= "            }\n";
                            break;
                        case 'BETWEEN':
                            $searchers .= "            if (is_array(\$value) && count(\$value) == 2) {\n";
                            $searchers .= "                \$query->whereBetween('{$columnName}', \$value);\n";
                            $searchers .= "            }\n";
                            break;
                        case 'NOT BETWEEN':
                            $searchers .= "            if (is_array(\$value) && count(\$value) == 2) {\n";
                            $searchers .= "                \$query->whereNotBetween('{$columnName}', \$value);\n";
                            $searchers .= "            }\n";
                            break;
                        default:
                            $searchers .= "            \$query->where('{$columnName}', 'LIKE', '%' . \$value . '%');\n";
                            break;
                    }
                    
                    $searchers .= "        }\n";
                    $searchers .= "        return \$query;\n";
                    $searchers .= "    }\n\n";
                }
            }
        }
        
        // 添加到数据中
        $data['searchers'] = $searchers;
        
        // 生成关联方法
        $relations = '';
        if (isset($this->config['relations']) && !empty($this->config['relations'])) {
            foreach ($this->config['relations'] as $relation) {
                if (isset($relation['type']) && isset($relation['model']) && isset($relation['foreign_key']) && isset($relation['local_key'])) {
                    $relationType = $relation['type'];
                    $relationModel = $relation['model'];
                    $foreignKey = $relation['foreign_key'];
                    $localKey = $relation['local_key'];
                    // 优化关联方法名称，只使用模型类名的小写形式
                    $modelParts = explode('\\', $relationModel);
                    $methodName = lcfirst(end($modelParts));
                    
                    // 如果是hasMany关系，添加s后缀
                    if ($relationType === 'hasMany') {
                        $methodName .= 's';
                    }
                    
                    // 生成关联方法
                    $relations .= "    /**\n";
                    $relations .= "     * 关联 {$relationModel}\n";
                    $relations .= "     */\n";
                    $relations .= "    public function {$methodName}()\n";
                    $relations .= "    {\n";
                    $relations .= "        return \$this->{$relationType}('{$relationModel}', '{$foreignKey}', '{$localKey}');\n";
                    $relations .= "    }\n\n";
                }
            }
        }
        
        // 生成软删除功能
        $softDelete = '';
        $softDeleteUse = '';
        $softDeleteTrait = '';
        $config = $this->config['config'] ?? [];
        if (isset($config['is_delete']) && $config['is_delete'] == 1) {
            $deleteColumn = $config['delete_column_name'] ?? 'deleted_at';
            $softDeleteUse = "use Illuminate\\Database\\Eloquent\\SoftDeletes;\n";
            $softDeleteTrait = "    /**\n";
            $softDeleteTrait .= "     * 启用软删除\n";
            $softDeleteTrait .= "     */\n";
            $softDeleteTrait .= "    use SoftDeletes;\n\n";
            $softDelete .= "    /**\n";
            $softDelete .= "     * 软删除字段\n";
            $softDelete .= "     */\n";
            $softDelete .= "    const DELETED_AT = '{$deleteColumn}';\n\n";
        }
        
        // 添加到数据中
        $data['soft_delete_use'] = $softDeleteUse;
        $data['soft_delete_trait'] = $softDeleteTrait;
        $data['soft_delete'] = $softDelete;
        $data['relations'] = $relations;
        
        return $this->templateRenderer->render('server/model/model.stub', $data);
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
