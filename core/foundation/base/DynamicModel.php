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
namespace core\foundation\base;

use core\foundation\base\BaseModel;
use core\business\service\DynamicTableService;
use core\business\tenant\context\TenantContext;
use core\foundation\exception\TenantException;

/**
 * 动态模型基类
 * 
 * 用于运行时动态创建的业务模型
 * 支持动态表名、动态字段等特性
 *
 */
class DynamicModel extends BaseModel
{
    /**
     * 动态表名
     * @var string
     */
    protected $dynamicTable;
    
    /**
     * 动态表前缀
     * @var string
     */
    protected $tablePrefix = 'saas_';
    
    /**
     * 动态表名后缀
     * @var string
     */
    protected $tableSuffix = '';
    
    /**
     * 是否启用时间戳
     * @var bool
     */
    protected $autoWriteTimestamp = true;
    
    /**
     * 创建时间字段
     * @var string
     */
    protected $createTime = 'create_time';
    
    /**
     * 更新时间字段
     * @var string
     */
    protected $updateTime = 'update_time';
    
    /**
     * 时间戳格式
     * @var string
     */
    protected $dateFormat = 'Y-m-d H:i:s';
    
    /**
     * 是否启用软删除
     * @var bool
     */
    protected $softDelete = false;
    
    /**
     * 租户标识字段
     * @var string
     */
    protected $tenantColumn = 'tenant_id';
    
    /**
     * 缓存 Key
     * @var string
     */
    protected $schemaCacheKey = 'dynamic_table_schema:';
    
    /**
     * 动态表服务
     * @var DynamicTableService|null
     */
    protected static $dynamicTableService;
    
    /**
     * 获取动态表服务实例
     * 
     * @return DynamicTableService
     */
    protected static function getDynamicTableService(): DynamicTableService
    {
        if (self::$dynamicTableService === null) {
            self::$dynamicTableService = new DynamicTableService();
        }
        return self::$dynamicTableService;
    }
    
    /**
     * 设置动态表名
     * 
     * @param string $table
     * @return $this
     */
    public function setDynamicTable(string $table): self
    {
        $this->dynamicTable = $table;
        $this->table = $this->buildTableName($table);
        return $this;
    }
    
    /**
     * 获取动态表名
     * 
     * @return string
     */
    public function getDynamicTable(): string
    {
        return $this->dynamicTable ?? '';
    }
    
    /**
     * 构建完整表名
     * 
     * @param string $table
     * @return string
     */
    protected function buildTableName(string $table): string
    {
        $tableName = $this->tablePrefix . $table . $this->tableSuffix;
        
        // 添加租户后缀（如果是库隔离模式）
        if (TenantContext::getIsolationMode() === 'database') {
            $tenantId = TenantContext::getTenantId();
            if ($tenantId !== null) {
                $tableName .= '_t' . $tenantId;
            }
        }
        
        return $tableName;
    }
    
    /**
     * 设置表名前缀
     * 
     * @param string $prefix
     * @return $this
     */
    public function setTablePrefix(string $prefix): self
    {
        $this->tablePrefix = $prefix;
        return $this;
    }
    
    /**
     * 设置表名后缀
     * 
     * @param string $suffix
     * @return $this
     */
    public function setTableSuffix(string $suffix): self
    {
        $this->tableSuffix = $suffix;
        return $this;
    }
    
    /**
     * 获取表名
     * 重写以支持动态表名
     */
    public function getName()
    {
        return $this->dynamicTable ?? parent::getName();
    }
    
    /**
     * 静态创建动态模型
     * 
     * @param string $table 表名
     * @param array $schema 表结构
     * @return static
     */
    public static function createDynamic(string $table, array $schema = [])
    {
        $model = new static();
        $model->setDynamicTable($table);
        
        // 如果提供了表结构，更新模型属性
        if (!empty($schema)) {
            $model->parseSchema($schema);
        }
        
        return $model;
    }
    
    /**
     * 解析表结构
     * 
     * @param array $schema
     * @return void
     */
    protected function parseSchema(array $schema): void
    {
        if (isset($schema['pk'])) {
            $this->pk = $schema['pk'];
        }
        
        if (isset($schema['autoWriteTimestamp'])) {
            $this->autoWriteTimestamp = $schema['autoWriteTimestamp'];
        }
        
        if (isset($schema['createTime'])) {
            $this->createTime = $schema['createTime'];
        }
        
        if (isset($schema['updateTime'])) {
            $this->updateTime = $schema['updateTime'];
        }
        
        if (isset($schema['softDelete'])) {
            $this->softDelete = $schema['softDelete'];
        }
    }
    
    /**
     * 检查动态表是否存在
     * 
     * @param string $table
     * @return bool
     */
    public static function tableExists(string $table): bool
    {
        return self::getDynamicTableService()->tableExists($table);
    }
    
    /**
     * 获取动态表结构
     * 
     * @param string $table
     * @return array
     */
    public static function getTableSchema(string $table): array
    {
        $cacheKey = self::$schemaCacheKey . $table;
        $ttl = config('tenant.cache.ttl', 3600);
        
        try {
            return cache()->remember($cacheKey, $ttl, function () use ($table) {
                return self::getDynamicTableService()->getTableSchema($table);
            });
        } catch (\Exception $e) {
            return self::getDynamicTableService()->getTableSchema($table);
        }
    }
    
    /**
     * 创建动态表
     * 
     * @param string $table 表名
     * @param array $fields 字段定义
     * @param array $options 表选项
     * @return bool
     */
    public static function createTable(string $table, array $fields, array $options = []): bool
    {
        $result = self::getDynamicTableService()->createTable($table, $fields, $options);
        
        // 清除表结构缓存
        cache()->delete(self::$schemaCacheKey . $table);
        
        return $result;
    }
    
    /**
     * 更新动态表结构
     * 
     * @param string $table
     * @param array $changes
     * @return bool
     */
    public static function updateTableSchema(string $table, array $changes): bool
    {
        $result = self::getDynamicTableService()->updateTableSchema($table, $changes);
        
        // 清除表结构缓存
        cache()->delete(self::$schemaCacheKey . $table);
        
        return $result;
    }
    
    /**
     * 删除动态表
     * 
     * @param string $table
     * @return bool
     */
    public static function dropTable(string $table): bool
    {
        $result = self::getDynamicTableService()->dropTable($table);
        
        // 清除表结构缓存
        cache()->delete(self::$schemaCacheKey . $table);
        
        return $result;
    }
    
    /**
     * 动态添加字段
     * 
     * @param string $field
     * @param string $type
     * @param array $options
     * @return bool
     */
    public function addField(string $field, string $type, array $options = []): bool
    {
        $table = $this->getDynamicTable();
        if (!$table) {
            throw new TenantException('Dynamic table name not set');
        }
        
        $result = self::getDynamicTableService()->addField($table, $field, $type, $options);
        
        // 清除表结构缓存
        cache()->delete(self::$schemaCacheKey . $table);
        
        return $result;
    }
    
    /**
     * 动态删除字段
     * 
     * @param string $field
     * @return bool
     */
    public function dropField(string $field): bool
    {
        $table = $this->getDynamicTable();
        if (!$table) {
            throw new TenantException('Dynamic table name not set');
        }
        
        $result = self::getDynamicTableService()->dropField($table, $field);
        
        // 清除表结构缓存
        cache()->delete(self::$schemaCacheKey . $table);
        
        return $result;
    }
    
    /**
     * 动态修改字段
     * 
     * @param string $field
     * @param string $type
     * @param array $options
     * @return bool
     */
    public function modifyField(string $field, string $type, array $options = []): bool
    {
        $table = $this->getDynamicTable();
        if (!$table) {
            throw new TenantException('Dynamic table name not set');
        }
        
        $result = self::getDynamicTableService()->modifyField($table, $field, $type, $options);
        
        // 清除表结构缓存
        cache()->delete(self::$schemaCacheKey . $table);
        
        return $result;
    }
    
    /**
     * 获取可用字段列表
     * 
     * @return array
     */
    public function getAvailableFields(): array
    {
        $table = $this->getDynamicTable();
        if (!$table) {
            return [];
        }
        
        $schema = self::getTableSchema($table);
        
        // 过滤掉系统字段和未授权字段
        $systemFields = ['tenant_id', 'create_time', 'update_time', 'delete_time', 'id'];
        $allowedFields = [];
        
        foreach ($schema as $field => $definition) {
            if (in_array($field, $systemFields)) {
                continue;
            }
            
            // 检查字段权限
            if ($this->checkFieldPermission($field)) {
                $allowedFields[$field] = $definition;
            }
        }
        
        return $allowedFields;
    }
    
    /**
     * 检查字段权限
     * 
     * @param string $field
     * @return bool
     */
    protected function checkFieldPermission(string $field): bool
    {
        // 检查功能订阅
        if (!config('tenant.subscription.enabled', true)) {
            return true;
        }
        
        $tenantId = TenantContext::getTenantId();
        if ($tenantId === null) {
            return true;
        }
        
        // 从订阅权限中检查
        $subscription = TenantContext::getSubscription();
        if (empty($subscription) || empty($subscription['allowed_fields'])) {
            return true;
        }
        
        $table = $this->getDynamicTable();
        $allowedFields = $subscription['allowed_fields'][$table] ?? [];
        
        // 如果没有为该表设置字段限制，允许所有字段
        if ($allowedFields === '*' || empty($allowedFields)) {
            return true;
        }
        
        return in_array($field, $allowedFields);
    }
    
    /**
     * 验证字段值
     * 
     * @param string $field
     * @param mixed $value
     * @return bool
     */
    public function validateField(string $field, $value): bool
    {
        $schema = self::getTableSchema($this->getDynamicTable());
        
        if (!isset($schema[$field])) {
            return false;
        }
        
        $definition = $schema[$field];
        
        // 类型验证
        $type = $definition['type'] ?? 'string';
        return $this->validateType($value, $type, $definition);
    }
    
    /**
     * 类型验证
     * 
     * @param mixed $value
     * @param string $type
     * @param array $definition
     * @return bool
     */
    protected function validateType($value, string $type, array $definition): bool
    {
        switch (strtolower($type)) {
            case 'integer':
            case 'int':
                return is_numeric($value);
                
            case 'float':
            case 'decimal':
                return is_numeric($value);
                
            case 'string':
            case 'text':
                return is_string($value);
                
            case 'boolean':
                return is_bool($value) || in_array($value, [0, 1, '0', '1', 'true', 'false']);
                
            case 'date':
            case 'datetime':
                return strtotime($value) !== false;
                
            case 'json':
                if (is_string($value)) {
                    json_decode($value);
                    return json_last_error() === JSON_ERROR_NONE;
                }
                return is_array($value) || is_object($value);
                
            default:
                return true;
        }
    }
    
    /**
     * 获取字段定义
     * 
     * @param string $field
     * @return array|null
     */
    public function getFieldDefinition(string $field): ?array
    {
        $schema = self::getTableSchema($this->getDynamicTable());
        return $schema[$field] ?? null;
    }
    
    /**
     * 获取字段列表
     * 
     * @return array
     */
    public function getFields(): array
    {
        return array_keys(self::getTableSchema($this->getDynamicTable()));
    }
    
    /**
     * 清除表结构缓存
     * 
     * @param string|null $table
     * @return void
     */
    public static function clearSchemaCache(?string $table = null): void
    {
        if ($table) {
            cache()->delete(self::$schemaCacheKey . $table);
        } else {
            // 清除所有动态表缓存
            // 注意：这里需要根据实际缓存驱动实现
        }
    }
}
