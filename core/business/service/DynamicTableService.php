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
namespace core\business\service;

use core\infrastructure\cache\CacheService;
use core\foundation\exception\TenantException;

/**
 * 动态表服务
 * 
 * 提供动态创建、修改、删除数据表的能力
 * 支持动态字段管理等
 * 
 * 文档位置: docs/saas/08-模型设计.md
 */
class DynamicTableService
{
    /**
     * 表前缀
     * @var string
     */
    protected string $tablePrefix = 'saas_';

    /**
     * 缓存服务
     */
    protected ?CacheService $cache = null;

    /**
     * 初始化
     */
    public function __construct(?CacheService $cache = null)
    {
        $this->tablePrefix = config('database.prefix', 'saas_');
        $this->cache = $cache;
    }

    /**
     * 获取缓存服务实例
     */
    protected function getCache(): CacheService
    {
        if ($this->cache === null) {
            $this->cache = new CacheService();
        }
        return $this->cache;
    }
    
    /**
     * 检查表是否存在
     * 
     * @param string $table
     * @return bool
     */
    public function tableExists(string $table): bool
    {
        $tableName = $this->getFullTableName($table);
        
        try {
            $result = Db::query("SHOW TABLES LIKE ?", [$tableName]);
            return !empty($result);
        } catch (\Exception $e) {
            return false;
        }
    }
    
    /**
     * 获取完整表名
     * 
     * @param string $table
     * @return string
     */
    protected function getFullTableName(string $table): string
    {
        if (strpos($table, $this->tablePrefix) === 0) {
            return $table;
        }
        return $this->tablePrefix . $table;
    }
    
    /**
     * 获取表结构
     * 
     * @param string $table
     * @return array
     */
    public function getTableSchema(string $table): array
    {
        $tableName = $this->getFullTableName($table);
        
        try {
            $columns = Db::query("SHOW FULL COLUMNS FROM `{$tableName}`");
            
            $schema = [];
            foreach ($columns as $column) {
                $schema[$column['Field']] = [
                    'name' => $column['Field'],
                    'type' => $this->parseFieldType($column['Type']),
                    'full_type' => $column['Type'],
                    'nullable' => $column['Null'] === 'YES',
                    'default' => $column['Default'],
                    'comment' => $column['Comment'],
                    'primary' => strtolower($column['Key']) === 'pri',
                    'auto_increment' => stripos($column['Extra'], 'auto_increment') !== false,
                ];
            }
            
            return $schema;
        } catch (\Exception $e) {
            throw new TenantException('Failed to get table schema: ' . $e->getMessage());
        }
    }
    
    /**
     * 解析字段类型
     * 
     * @param string $type
     * @return string
     */
    protected function parseFieldType(string $type): string
    {
        $type = strtolower($type);
        
        $typeMap = [
            'int' => 'integer',
            'bigint' => 'integer',
            'smallint' => 'integer',
            'tinyint' => 'integer',
            'decimal' => 'decimal',
            'float' => 'float',
            'double' => 'float',
            'varchar' => 'string',
            'char' => 'string',
            'text' => 'text',
            'mediumtext' => 'text',
            'longtext' => 'text',
            'date' => 'date',
            'datetime' => 'datetime',
            'timestamp' => 'datetime',
            'time' => 'string',
            'year' => 'integer',
            'enum' => 'enum',
            'set' => 'set',
            'json' => 'json',
            'blob' => 'binary',
        ];
        
        // 提取基本类型
        $baseType = preg_replace('/\(.*\)/', '', $type);
        $baseType = trim($baseType);
        
        return $typeMap[$baseType] ?? 'string';
    }
    
    /**
     * 创建表
     * 
     * @param string $table 表名
     * @param array $fields 字段定义
     * @param array $options 表选项
     * @return bool
     */
    public function createTable(string $table, array $fields, array $options = []): bool
    {
        $tableName = $this->getFullTableName($table);
        
        if ($this->tableExists($table)) {
            throw new TenantException("Table {$tableName} already exists");
        }
        
        // 构建 SQL
        $sql = $this->buildCreateTableSql($tableName, $fields, $options);
        
        try {
            Db::execute($sql);
            return true;
        } catch (\Exception $e) {
            throw new TenantException('Failed to create table: ' . $e->getMessage());
        }
    }
    
    /**
     * 构建创建表的 SQL
     * 
     * @param string $tableName
     * @param array $fields
     * @param array $options
     * @return string
     */
    protected function buildCreateTableSql(string $tableName, array $fields, array $options): string
    {
        $columns = [];
        $primaryKeys = [];
        
        foreach ($fields as $name => $definition) {
            $columnSql = $this->buildColumnSql($name, $definition);
            $columns[] = $columnSql;
            
            if (isset($definition['primary']) && $definition['primary']) {
                $primaryKeys[] = "`{$name}`";
            }
        }
        
        // 添加租户隔离字段
        $columns[] = "`tenant_id` bigint(20) unsigned NOT NULL DEFAULT 0 COMMENT '租户ID' AFTER `id`";
        
        // 添加时间戳字段
        $columns[] = "`create_time` int(11) unsigned NOT NULL DEFAULT 0 COMMENT '创建时间'";
        $columns[] = "`update_time` int(11) unsigned NOT NULL DEFAULT 0 COMMENT '更新时间'";
        
        // 主键
        if (!empty($primaryKeys)) {
            $columns[] = "PRIMARY KEY (`id`, `tenant_id`)";
        } else {
            $columns[] = "PRIMARY KEY (`id`)";
        }
        
        // 索引
        if (isset($options['indexes'])) {
            foreach ($options['indexes'] as $index) {
                $columns[] = $this->buildIndexSql($index);
            }
        }
        
        // 表选项
        $tableOptions = $this->buildTableOptions($options);
        
        return sprintf(
            "CREATE TABLE IF NOT EXISTS `{$tableName}` (\n%s\n) {$tableOptions}",
            implode(",\n", $columns)
        );
    }
    
    /**
     * 构建字段 SQL
     * 
     * @param string $name
     * @param array $definition
     * @return string
     */
    protected function buildColumnSql(string $name, array $definition): string
    {
        $type = $definition['type'] ?? 'varchar';
        $length = $definition['length'] ?? null;
        $default = $definition['default'] ?? null;
        $nullable = $definition['nullable'] ?? true;
        $comment = $definition['comment'] ?? '';
        $autoIncrement = $definition['auto_increment'] ?? false;
        $primary = $definition['primary'] ?? false;
        
        // 构建类型
        $typeSql = $this->buildTypeSql($type, $length);
        
        // 构建字段定义
        $parts = [$typeSql];
        
        // 可空性
        if (!$nullable) {
            $parts[] = 'NOT NULL';
        } else {
            $parts[] = 'NULL';
        }
        
        // 默认值
        if ($default !== null) {
            if (is_string($default) && strtoupper($default) !== 'NULL') {
                $parts[] = "DEFAULT '{$default}'";
            } elseif (is_numeric($default)) {
                $parts[] = "DEFAULT {$default}";
            } elseif (strtoupper($default) === 'NULL') {
                $parts[] = "DEFAULT NULL";
            } elseif ($default === 'CURRENT_TIMESTAMP') {
                $parts[] = "DEFAULT CURRENT_TIMESTAMP";
            }
        }
        
        // 自增
        if ($autoIncrement) {
            $parts[] = 'AUTO_INCREMENT';
        }
        
        // 注释
        if ($comment) {
            $parts[] = "COMMENT '{$comment}'";
        }
        
        return sprintf("`%s` %s", $name, implode(' ', $parts));
    }
    
    /**
     * 构建类型 SQL
     * 
     * @param string $type
     * @param mixed $length
     * @return string
     */
    protected function buildTypeSql(string $type, $length): string
    {
        $type = strtolower($type);
        
        switch ($type) {
            case 'integer':
            case 'int':
                return $length ? "int({$length})" : 'int(11)';
                
            case 'bigint':
                return $length ? "bigint({$length})" : 'bigint(20)';
                
            case 'smallint':
                return $length ? "smallint({$length})" : 'smallint(6)';
                
            case 'tinyint':
                return $length ? "tinyint({$length})" : 'tinyint(4)';
                
            case 'decimal':
                $precision = $length['precision'] ?? 10;
                $scale = $length['scale'] ?? 2;
                return "decimal({$precision},{$scale})";
                
            case 'float':
                return $length ? "float({$length})" : 'float';
                
            case 'double':
                return $length ? "double({$length})" : 'double';
                
            case 'string':
            case 'varchar':
                $len = $length ?? 255;
                return "varchar({$len})";
                
            case 'char':
                $len = $length ?? 32;
                return "char({$len})";
                
            case 'text':
                return 'text';
                
            case 'mediumtext':
                return 'mediumtext';
                
            case 'longtext':
                return 'longtext';
                
            case 'date':
                return 'date';
                
            case 'datetime':
                return 'datetime';
                
            case 'timestamp':
                return 'timestamp';
                
            case 'time':
                return 'time';
                
            case 'year':
                return 'year(4)';
                
            case 'enum':
                $values = is_array($length) ? $length : [$length];
                $enumValues = array_map(function ($v) {
                    return "'{$v}'";
                }, $values);
                return 'enum(' . implode(',', $enumValues) . ')';
                
            case 'set':
                $values = is_array($length) ? $length : [$length];
                $setValues = array_map(function ($v) {
                    return "'{$v}'";
                }, $values);
                return 'set(' . implode(',', $setValues) . ')';
                
            case 'json':
                return 'json';
                
            case 'binary':
            case 'blob':
                return 'blob';
                
            case 'mediumblob':
                return 'mediumblob';
                
            case 'longblob':
                return 'longblob';
                
            default:
                return 'varchar(255)';
        }
    }
    
    /**
     * 构建索引 SQL
     * 
     * @param array $index
     * @return string
     */
    protected function buildIndexSql(array $index): string
    {
        $type = $index['type'] ?? 'index';
        $columns = $index['columns'] ?? [];
        $name = $index['name'] ?? '';
        
        $columnList = implode('`,`', $columns);
        
        switch (strtolower($type)) {
            case 'unique':
                $name = $name ?: 'uniq_' . implode('_', $columns);
                return sprintf("UNIQUE KEY `%s` (`%s`)", $name, $columnList);
                
            case 'fulltext':
                $name = $name ?: 'fulltext_' . implode('_', $columns);
                return sprintf("FULLTEXT KEY `%s` (`%s`)", $name, $columnList);
                
            case 'primary':
                return sprintf("PRIMARY KEY (`%s`)", $columnList);
                
            default:
                $name = $name ?: 'idx_' . implode('_', $columns);
                return sprintf("KEY `%s` (`%s`)", $name, $columnList);
        }
    }
    
    /**
     * 构建表选项
     * 
     * @param array $options
     * @return string
     */
    protected function buildTableOptions(array $options): string
    {
        $charset = $options['charset'] ?? config('database.charset', 'utf8mb4');
        $collate = $options['collate'] ?? $charset . '_unicode_ci';
        $engine = $options['engine'] ?? 'InnoDB';
        $comment = $options['comment'] ?? '';
        
        $parts = [
            "ENGINE={$engine}",
            "DEFAULT CHARSET={$charset}",
            "COLLATE={$collate}",
        ];
        
        if ($comment) {
            $parts[] = "COMMENT='{$comment}'";
        }
        
        return implode(' ', $parts);
    }
    
    /**
     * 添加字段
     * 
     * @param string $table
     * @param string $field
     * @param string $type
     * @param array $options
     * @return bool
     */
    public function addField(string $table, string $field, string $type, array $options = []): bool
    {
        $tableName = $this->getFullTableName($table);
        
        if (!$this->tableExists($table)) {
            throw new TenantException("Table {$tableName} does not exist");
        }
        
        if ($this->fieldExists($table, $field)) {
            throw new TenantException("Field {$field} already exists in table {$tableName}");
        }
        
        $position = $options['after'] ?? null;
        $definition = array_merge($options, ['type' => $type]);
        $columnSql = $this->buildColumnSql($field, $definition);
        
        $sql = "ALTER TABLE `{$tableName}` ADD COLUMN {$columnSql}";
        
        if ($position) {
            $sql .= " AFTER `{$position}`";
        }
        
        try {
            Db::execute($sql);
            return true;
        } catch (\Exception $e) {
            throw new TenantException('Failed to add field: ' . $e->getMessage());
        }
    }
    
    /**
     * 删除字段
     * 
     * @param string $table
     * @param string $field
     * @return bool
     */
    public function dropField(string $table, string $field): bool
    {
        $tableName = $this->getFullTableName($table);
        
        if (!$this->tableExists($table)) {
            throw new TenantException("Table {$tableName} does not exist");
        }
        
        if (!$this->fieldExists($table, $field)) {
            throw new TenantException("Field {$field} does not exist in table {$tableName}");
        }
        
        try {
            Db::execute("ALTER TABLE `{$tableName}` DROP COLUMN `{$field}`");
            return true;
        } catch (\Exception $e) {
            throw new TenantException('Failed to drop field: ' . $e->getMessage());
        }
    }
    
    /**
     * 修改字段
     * 
     * @param string $table
     * @param string $field
     * @param string $type
     * @param array $options
     * @return bool
     */
    public function modifyField(string $table, string $field, string $type, array $options = []): bool
    {
        $tableName = $this->getFullTableName($table);
        
        if (!$this->tableExists($table)) {
            throw new TenantException("Table {$tableName} does not exist");
        }
        
        $definition = array_merge($options, ['type' => $type]);
        $columnSql = $this->buildColumnSql($field, $definition);
        
        try {
            Db::execute("ALTER TABLE `{$tableName}` MODIFY COLUMN {$columnSql}");
            return true;
        } catch (\Exception $e) {
            throw new TenantException('Failed to modify field: ' . $e->getMessage());
        }
    }
    
    /**
     * 重命名字段
     * 
     * @param string $table
     * @param string $oldField
     * @param string $newField
     * @param string $type
     * @param array $options
     * @return bool
     */
    public function renameField(string $table, string $oldField, string $newField, string $type, array $options = []): bool
    {
        $tableName = $this->getFullTableName($table);
        
        $definition = array_merge($options, ['type' => $type]);
        $columnSql = $this->buildColumnSql($newField, $definition);
        
        try {
            Db::execute("ALTER TABLE `{$tableName}` CHANGE COLUMN `{$oldField}` {$columnSql}");
            return true;
        } catch (\Exception $e) {
            throw new TenantException('Failed to rename field: ' . $e->getMessage());
        }
    }
    
    /**
     * 添加索引
     * 
     * @param string $table
     * @param array $index
     * @return bool
     */
    public function addIndex(string $table, array $index): bool
    {
        $tableName = $this->getFullTableName($table);
        
        if (!$this->tableExists($table)) {
            throw new TenantException("Table {$tableName} does not exist");
        }
        
        $sql = "ALTER TABLE `{$tableName}` ADD " . $this->buildIndexSql($index);
        
        try {
            Db::execute($sql);
            return true;
        } catch (\Exception $e) {
            throw new TenantException('Failed to add index: ' . $e->getMessage());
        }
    }
    
    /**
     * 删除索引
     * 
     * @param string $table
     * @param string $indexName
     * @return bool
     */
    public function dropIndex(string $table, string $indexName): bool
    {
        $tableName = $this->getFullTableName($table);
        
        if (!$this->tableExists($table)) {
            throw new TenantException("Table {$tableName} does not exist");
        }
        
        try {
            Db::execute("ALTER TABLE `{$tableName}` DROP INDEX `{$indexName}`");
            return true;
        } catch (\Exception $e) {
            throw new TenantException('Failed to drop index: ' . $e->getMessage());
        }
    }
    
    /**
     * 检查字段是否存在
     * 
     * @param string $table
     * @param string $field
     * @return bool
     */
    public function fieldExists(string $table, string $field): bool
    {
        $schema = $this->getTableSchema($table);
        return isset($schema[$field]);
    }
    
    /**
     * 更新表结构
     * 
     * @param string $table
     * @param array $changes
     * @return bool
     */
    public function updateTableSchema(string $table, array $changes): bool
    {
        // 处理新增字段
        if (isset($changes['add_fields'])) {
            foreach ($changes['add_fields'] as $field => $definition) {
                $this->addField($table, $field, $definition['type'], $definition);
            }
        }
        
        // 处理删除字段
        if (isset($changes['drop_fields'])) {
            foreach ($changes['drop_fields'] as $field) {
                $this->dropField($table, $field);
            }
        }
        
        // 处理修改字段
        if (isset($changes['modify_fields'])) {
            foreach ($changes['modify_fields'] as $field => $definition) {
                $this->modifyField($table, $field, $definition['type'], $definition);
            }
        }
        
        // 处理重命名字段
        if (isset($changes['rename_fields'])) {
            foreach ($changes['rename_fields'] as $old => $new) {
                if (is_array($new)) {
                    $this->renameField($table, $old, $new['name'], $new['type'], $new);
                } else {
                    $schema = $this->getTableSchema($table);
                    $type = $schema[$old]['type'] ?? 'varchar';
                    $this->renameField($table, $old, $new, $type);
                }
            }
        }
        
        // 处理索引变更
        if (isset($changes['add_indexes'])) {
            foreach ($changes['add_indexes'] as $index) {
                $this->addIndex($table, $index);
            }
        }
        
        if (isset($changes['drop_indexes'])) {
            foreach ($changes['drop_indexes'] as $indexName) {
                $this->dropIndex($table, $indexName);
            }
        }
        
        return true;
    }
    
    /**
     * 删除表
     * 
     * @param string $table
     * @return bool
     */
    public function dropTable(string $table): bool
    {
        $tableName = $this->getFullTableName($table);
        
        if (!$this->tableExists($table)) {
            return true; // 表不存在，视为成功
        }
        
        try {
            Db::execute("DROP TABLE IF EXISTS `{$tableName}`");
            return true;
        } catch (\Exception $e) {
            throw new TenantException('Failed to drop table: ' . $e->getMessage());
        }
    }
    
    /**
     * 重命名表
     * 
     * @param string $oldTable
     * @param string $newTable
     * @return bool
     */
    public function renameTable(string $oldTable, string $newTable): bool
    {
        $oldName = $this->getFullTableName($oldTable);
        $newName = $this->getFullTableName($newTable);
        
        if (!$this->tableExists($oldTable)) {
            throw new TenantException("Table {$oldName} does not exist");
        }
        
        try {
            Db::execute("RENAME TABLE `{$oldName}` TO `{$newName}`");
            return true;
        } catch (\Exception $e) {
            throw new TenantException('Failed to rename table: ' . $e->getMessage());
        }
    }
    
    /**
     * 获取表列表（原始列表）
     * 
     * @param string $pattern
     * @return array
     */
    public function getTableNames(string $pattern = ''): array
    {
        try {
            if ($pattern) {
                $fullPattern = $this->getFullTableName($pattern);
                $result = Db::query("SHOW TABLES LIKE ?", [$fullPattern]);
            } else {
                $prefix = $this->tablePrefix . '%';
                $result = Db::query("SHOW TABLES LIKE ?", [$prefix]);
            }
            
            $tables = [];
            foreach ($result as $row) {
                $tables[] = array_values($row)[0];
            }
            
            return $tables;
        } catch (\Exception $e) {
            return [];
        }
    }
    
    /**
     * 复制表结构
     * 
     * @param string $sourceTable
     * @param string $targetTable
     * @return bool
     */
    public function cloneTable(string $sourceTable, string $targetTable): bool
    {
        $sourceName = $this->getFullTableName($sourceTable);
        $targetName = $this->getFullTableName($targetTable);
        
        if (!$this->tableExists($sourceTable)) {
            throw new TenantException("Source table {$sourceName} does not exist");
        }
        
        if ($this->tableExists($targetTable)) {
            throw new TenantException("Target table {$targetName} already exists");
        }
        
        try {
            Db::execute("CREATE TABLE `{$targetName}` LIKE `{$sourceName}`");
            return true;
        } catch (\Exception $e) {
            throw new TenantException('Failed to clone table: ' . $e->getMessage());
        }
    }
    
    /**
     * 获取表信息
     * 
     * @param string $table
     * @return array
     */
    public function getTableInfo(string $table): array
    {
        $tableName = $this->getFullTableName($table);
        
        try {
            $result = Db::query("SHOW TABLE STATUS FROM " . config('database.database') . " LIKE ?", [$tableName]);
            
            if (empty($result)) {
                return [];
            }
            
            $info = $result[0];
            return [
                'name' => $info['Name'],
                'engine' => $info['Engine'],
                'row_format' => $info['Row_format'],
                'rows' => $info['Rows'],
                'data_length' => $info['Data_length'],
                'index_length' => $info['Index_length'],
                'data_free' => $info['Data_free'],
                'auto_increment' => $info['Auto_increment'],
                'create_time' => $info['Create_time'],
                'update_time' => $info['Update_time'],
                'comment' => $info['Comment'],
            ];
        } catch (\Exception $e) {
            throw new TenantException('Failed to get table info: ' . $e->getMessage());
        }
    }
    
    /**
     * 绑定数据源到表
     * 
     * @param string $tableName 表名
     * @param string $database 数据源名称
     * @return array
     */
    public function bindDataSource(string $tableName, string $database): array
    {
        if (empty($tableName)) {
            throw new TenantException('表名不能为空');
        }
        
        if (empty($database)) {
            throw new TenantException('数据源不能为空');
        }
        
        // 验证表是否存在
        if (!$this->tableExists($tableName)) {
            throw new TenantException('表不存在');
        }
        
        // 验证数据源是否存在
        $dbSettingDao = new \app\dao\tenant\DbSettingDao();
        $dbSetting = $dbSettingDao->findByDatabase($database);
        
        if (!$dbSetting) {
            throw new TenantException('数据源不存在');
        }
        
        if (!$dbSetting->enabled) {
            throw new TenantException('数据源未启用');
        }
        
        // 保存绑定关系（可以存储在缓存或专门的绑定表中）
        $cacheKey = "table_datasource_binding:{$tableName}";
        $this->getCache()->set($cacheKey, $database, 0);
        
        return [
            'table_name' => $tableName,
            'database' => $database,
            'bound' => true,
            'bound_time' => date('Y-m-d H:i:s'),
        ];
    }
    
    /**
     * 解除数据源绑定
     * 
     * @param string $tableName 表名
     * @return bool
     */
    public function unbindDataSource(string $tableName): bool
    {
        if (empty($tableName)) {
            throw new TenantException('表名不能为空');
        }
        
        $cacheKey = "table_datasource_binding:{$tableName}";
        $this->getCache()->delete($cacheKey);
        
        return true;
    }
    
    /**
     * 获取表的数据源绑定状态统计
     * 
     * @return array
     */
    public function getBindingStats(): array
    {
        $tables = $this->getTableNames();
        $bound = 0;
        $unbound = 0;
        
        $cache = $this->getCache();
        
        foreach ($tables as $table) {
            $cacheKey = "table_datasource_binding:{$table}";
            if ($cache->get($cacheKey) !== null) {
                $bound++;
            } else {
                $unbound++;
            }
        }
        
        return [
            'total' => count($tables),
            'bound' => $bound,
            'unbound' => $unbound,
        ];
    }
    
    /**
     * 同步表结构到目标数据源
     * 
     * @param string $tableName 表名
     * @param string $targetDatabase 目标数据源
     * @return array
     */
    public function syncTableStructure(string $tableName, string $targetDatabase): array
    {
        if (empty($tableName)) {
            throw new TenantException('表名不能为空');
        }
        
        if (empty($targetDatabase)) {
            throw new TenantException('目标数据源不能为空');
        }
        
        // 获取源表结构
        $schema = $this->getTableSchema($tableName);
        
        // 获取目标数据源配置
        $dbSettingDao = new \app\dao\tenant\DbSettingDao();
        $dbSetting = $dbSettingDao->findByDatabase($targetDatabase);
        
        if (!$dbSetting) {
            throw new TenantException('目标数据源不存在');
        }
        
        // 在目标数据源创建表
        $config = $dbSetting->toConfig();
        $config['database'] = $targetDatabase;
        
        try {
            // 创建 PDO 连接
            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
                $config['host'],
                $config['port'],
                $config['database']
            );
            
            $pdo = new \PDO(
                $dsn,
                $config['username'],
                $config['password'],
                [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
            );
            
            // 构建 CREATE TABLE 语句
            $columns = [];
            $primaryKeys = [];
            
            foreach ($schema as $field => $definition) {
                $columnDef = "`{$field}` " . $definition['full_type'];
                
                if (!$definition['nullable']) {
                    $columnDef .= ' NOT NULL';
                }
                
                if ($definition['default'] !== null) {
                    $columnDef .= " DEFAULT '{$definition['default']}'";
                }
                
                if ($definition['auto_increment']) {
                    $columnDef .= ' AUTO_INCREMENT';
                }
                
                if ($definition['comment']) {
                    $columnDef .= " COMMENT '{$definition['comment']}'";
                }
                
                $columns[] = $columnDef;
                
                if ($definition['primary']) {
                    $primaryKeys[] = "`{$field}`";
                }
            }
            
            if (!empty($primaryKeys)) {
                $columns[] = 'PRIMARY KEY (' . implode(', ', $primaryKeys) . ')';
            }
            
            $sql = sprintf(
                "CREATE TABLE IF NOT EXISTS `%s` (\n%s\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
                $tableName,
                implode(",\n", $columns)
            );
            
            $pdo->exec($sql);
            
            return [
                'success' => true,
                'table' => $tableName,
                'target_database' => $targetDatabase,
                'synced_fields' => count($schema),
            ];
        } catch (\PDOException $e) {
            throw new TenantException('同步失败: ' . $e->getMessage());
        }
    }
    
    /**
     * 获取表列表（支持分页和筛选）
     * 
     * @param array $params 查询参数
     * @return array
     */
    public function getTableListWithPagination(array $params = []): array
    {
        $page = (int)($params['page'] ?? 1);
        $limit = (int)($params['limit'] ?? 20);
        $keyword = $params['keyword'] ?? '';
        
        $tables = $this->getTableNames();
        
        // 过滤
        if ($keyword) {
            $tables = array_filter($tables, function ($table) use ($keyword) {
                return stripos($table, $keyword) !== false;
            });
        }
        
        $tables = array_values($tables);
        $total = count($tables);
        
        // 分页
        $offset = ($page - 1) * $limit;
        $list = array_slice($tables, $offset, $limit);
        
        // 获取缓存状态
        $cache = $this->getCache();
        $result = [];
        
        foreach ($list as $table) {
            $cacheKey = "table_datasource_binding:{$table}";
            $boundDatabase = $cache->get($cacheKey);
            
            $result[] = [
                'table_name' => $table,
                'bound_database' => $boundDatabase,
                'is_bound' => $boundDatabase !== null,
            ];
        }
        
        return [
            'list' => $result,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
        ];
    }
}
