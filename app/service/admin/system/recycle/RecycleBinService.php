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

namespace app\service\admin\system\recycle;

use app\dao\system\recycle\RecycleBinDao;
use app\model\system\recycle\RecycleBin;
use core\business\tenant\context\TenantContext;
use core\foundation\base\BaseService;
use core\foundation\exception\handler\AdminException;
use support\Db;

/**
 * 回收站服务
 *
 * @author Mr.April
 * @since  1.0
 */
class RecycleBinService extends BaseService
{
    public function __construct(RecycleBinDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 数据恢复
     *
     * @param int $recycleId
     *
     * @throws \Throwable
     */
    public function restoreRecycleBin(int $recycleId): void
    {
        $this->transaction(function () use ($recycleId) {
            $record = $this->dao->get($recycleId, null, [], '');
            if (empty($record)) {
                throw new AdminException("回收站记录不存在");
            }
            // 3. 动态恢复数据
            $this->restoreOriginalData($record);

            // 4. 删除回收站记录
            $record->delete();
        });
    }

    /**
     * 恢复原始数据
     */
    protected function restoreOriginalData(RecycleBin $record): void
    {
        $tableName    = $record->table_name;
        $config       = self::getTableConfig($tableName);
        $connection   = $this->getConnectionName($record);
        $tableData    = json_decode($record->getData('data'), true);
        
        // 安全获取 relation_data 字段
        $relationData = [];
        try {
            $relationDataJson = $record->getData('relation_data');
            if ($relationDataJson) {
                $relationData = json_decode($relationDataJson, true) ?? [];
            }
        } catch (\Exception $e) {
            // 字段可能不存在，忽略错误
        }
        
        // 获取表的列信息（过滤掉不存在的字段）
        $columns = $this->getTableColumns($tableName, $connection);
        $tableData = array_intersect_key($tableData, array_flip($columns));
        
        // 恢复主表数据
        Db::connection($connection)->table($tableName)->insert($tableData);
        
        // 恢复关联表数据
        if (!empty($relationData)) {
            $this->restoreRelatedData($tableName, $relationData, $connection);
        }
    }

    /**
     * 恢复关联表数据
     *
     * @param string $tableName
     * @param array  $relationData
     * @param string $connection
     */
    protected function restoreRelatedData(string $tableName, array $relationData, string $connection): void
    {
        $config = self::getTableConfig($tableName);
        $relations = $config['relations'] ?? [];
        
        foreach ($relations as $relation) {
            $relationName = $relation['name'];
            $relatedData = $relationData[$relationName] ?? [];
            
            if (empty($relatedData)) {
                continue;
            }
            
            // 根据配置获取关联表名
            $relatedTable = $relation['related_table'] ?? '';
            
            if (empty($relatedTable)) {
                continue;
            }
            
            // 获取关联表列信息
            $relatedColumns = $this->getTableColumns($relatedTable, $connection);
            
            foreach ($relatedData as $item) {
                $item = array_intersect_key($item, array_flip($relatedColumns));
                if (!empty($item)) {
                    Db::connection($connection)->table($relatedTable)->insert($item);
                }
            }
        }
    }

    /**
     * 根据回收记录获取数据库连接名
     *
     * @param RecycleBin $record
     *
     * @return string
     */
    protected function getConnectionName(RecycleBin $record): string
    {
        $tenantMode = TenantContext::getIsolationMode();
        
        if ($tenantMode === 'database' && $record->tenant_id) {
            // 库隔离模式: 使用租户连接
            return 'tenant_' . $record->tenant_id;
        }
        
        // 非租户模式或字段模式: 使用主库连接
        return config('database.default');
    }

    /**
     * 获取还原表的数据列
     *
     * @param string $tableName
     * @param string $connection
     *
     * @return array
     */
    private function getTableColumns(string $tableName, string $connection = ''): array
    {
        return Db::connection($connection)->getSchemaBuilder()->getColumnListing($tableName);
    }

    /**
     * 获取表配置（合并全局+多租户+表级配置）
     *
     * @param string $table
     *
     * @return array
     */
    public static function getTableConfig(string $table): array
    {
        $tenantMode = TenantContext::getIsolationMode();
        
        return array_merge(
            config('recycle_bin.default', []),
            config("recycle_bin.tenant.{$tenantMode}", []),
            config("recycle_bin.tables.{$table}", []),
            ['tenant_mode' => $tenantMode]
        );
    }

}
