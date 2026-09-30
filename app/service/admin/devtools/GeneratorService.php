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

namespace app\service\admin\devtools;

use app\dao\devtools\GeneratorTableDao;
use core\business\generator\GeneratorEngine;
use core\foundation\base\BaseService;
use core\foundation\exception\handler\AdminException;
use support\Container;
use support\Db;
use ZipArchive;

class GeneratorService extends BaseService
{
    public function __construct(GeneratorTableDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 获取数据库表列表
     *
     * @param array $query 查询条件
     * @param int   $page  当前页码
     * @param int   $limit 每页数量
     *
     * @return array 分页数据
     */
    public function tableList(array $query = [], int $page = 1, int $limit = 10): array
    {
        try {
            $db       = !empty($query['source']) ? Db::connect($query['source']) : Db::connection();
            $sql      = 'show table status';
            $bindings = [];
            if (!empty($query['name'])) {
                $sql              .= ' where name = :name';
                $bindings['name'] = $query['name'];
            }
            $list = $db->select($sql, $bindings);
            $list = is_array($list) ? $list : [];
            $data = array_map(function ($item) {
                $item = (array)$item;
                return [
                    'name'         => $item['Name'] ?? '',
                    'engine'       => $item['Engine'] ?? '',
                    'rows'         => $item['Rows'] ?? 0,
                    'data_free'    => $item['Data_free'] ?? 0,
                    'data_length'  => $item['Data_length'] ?? 0,
                    'index_length' => $item['Index_length'] ?? 0,
                    'collation'    => $item['Collation'] ?? '',
                    'created_date' => $item['Create_time'] ?? '',
                    'updated_date' => $item['Update_time'] ?? '',
                    'comment'      => $item['Comment'] ?? '',
                ];
            }, $list);

            // 计算分页信息
            $total      = count($data);
            $last_page  = ceil($total / $limit);
            $startIndex = ($page - 1) * $limit;
            $pageData   = array_slice($data, $startIndex, $limit);

            // 返回标准化分页数据
            return [
                'items'     => $pageData,
                'total'     => $total,
                'page'      => $page,
                'limit'     => $limit,
                'last_page' => $last_page,
                'from'      => $startIndex + 1,
                'to'        => min($startIndex + $limit, $total),
            ];
        } catch (\Throwable $e) {
            return [
                'items'     => [],
                'total'     => 0,
                'page'      => $page,
                'limit'     => $limit,
                'last_page' => 0,
                'from'      => 0,
                'to'        => 0,
                'error'     => $e->getMessage(),
            ];
        }
    }

    /**
     * 添加代码生成
     *
     * @throws \Throwable
     * @throws \core\exception\handler\AdminException
     */
    public function store(array $data): void
    {
        $this->transaction(function () use ($data) {
            try {
                $tablePrefix = config('database.connections.mysql.prefix');
                $sql         = 'SHOW TABLE STATUS';
                $bindings    = [];

                if (!empty($data['name'])) {
                    $sql        .= " WHERE Name = ?";
                    $bindings[] = $data['name'];
                }

                $tables    = Db::select($sql, $bindings);
                $tableInfo = $tables[0] ?? [];
                if (empty($tableInfo)) {
                    throw new AdminException('DATA_NOT_EXIST');
                }

                $tableName = preg_replace("/^{$tablePrefix}/", '', $tableInfo->Name, 1);

                $columns = Db::select("SHOW FULL COLUMNS FROM `{$tableInfo->Name}`");
                $fields  = [];
                foreach ($columns as $column) {
                    $fields[] = [
                        'name'      => $column->Field,
                        'type'      => $column->Type,
                        'comment'   => $column->Comment ?? '',
                        'notnull'   => $column->Null === 'NO',
                        'primary'   => $column->Key === 'PRI',
                        'dict_type' => '',
                        'plugin'    => '',
                        'model'     => '',
                        'label_key' => '',
                        'value_key' => '',
                    ];
                }

                /** @var  $generateTableService GeneratorTableService */
                $generateTableService = Container::make(GeneratorTableService::class);
                $generateTable        = $generateTableService->get(['table_name' => $tableName]);
                if (!empty($generateTable)) {
                    //如果已经有了，就不重复创建
                    return;
                }

                // 使用模型关联创建数据
                $tableData = [
                    'table_name'    => $tableName,
                    'table_content' => $tableInfo->Comment,
                    'class_name'    => $tableName,
                    'module_name'   => $tableName,
                ];

                // 创建表记录
                $tableRecord = $generateTableService->save($tableData);

                // 准备列数据
                $columnsData    = [];
                $default_column = ['created_at', 'updated_at'];
                foreach ($fields as $v) {
                    $required = 0;
                    if ($v['notnull'] && !$v['primary'] && !in_array($v['name'], $default_column)) {
                        $required = 1;
                    }

                    $columnsData[] = [
                        'column_name'    => $v['name'],
                        'column_comment' => $v['comment'],
                        'column_type'    => $this->getFieldType($v['type']),
                        'is_required'    => $required,
                        'is_pk'          => $v['primary'] ? 1 : 0,
                        'is_insert'      => !in_array($v['name'], $default_column) ? 1 : 0,
                        'is_update'      => !in_array($v['name'], $default_column) ? 1 : 0,
                        'is_lists'       => !in_array($v['name'], $default_column) ? 1 : 0,
                        'is_delete'      => 0,
                        'query_type'     => '=',
                        'view_type'      => 'input',
                        'dict_type'      => $v['dict_type'] ?? '',
                        'plugin'         => $v['plugin'] ?? '',
                        'model'          => $v['model'] ?? '',
                        'label_key'      => $v['label_key'] ?? '',
                        'value_key'      => $v['value_key'] ?? '',
                    ];
                }
                $tableRecord->columns()->createMany($columnsData);
            } catch (\Throwable $e) {
                throw new AdminException($e->getMessage());
            }
        });
    }

    /**
     * 更新代码生成器表
     *
     * @param string $id
     * @param array  $data
     *
     * @throws \Throwable
     * @throws \core\exception\handler\AdminException
     */
    public function update(string $id, array $data): void
    {
        $this->transaction(function () use ($id, $data) {
            try {
                /** @var GeneratorTableService $generateTableService */
                $generateTableService = Container::make(GeneratorTableService::class);
                $table                = $generateTableService->get($id);
                if (empty($table)) {
                    throw new AdminException('DATA_NOT_EXIST');
                }

                // 处理软删除配置
                if (isset($data['config']['is_delete']) && $data['config']['is_delete'] == 1) {
                    $deleteColumnName = $data['config']['delete_column_name'] ?? '';

                    // 如果配置了软删除字段名，需要更新对应的columns字段
                    if (!empty($deleteColumnName)) {
                        // 重置所有字段的is_delete为0
                        $table->columns()->update(['is_delete' => 0]);

                        // 设置指定字段的is_delete为1
                        $table->columns()->where('column_name', $deleteColumnName)->update(['is_delete' => 1]);
                    }
                } else {
                    // 禁用软删除，重置所有字段的is_delete为0
                    $table->columns()->update(['is_delete' => 0]);
                }

                // 处理排序配置
                if (isset($data['config']['order_type']) && $data['config']['order_type'] > 0) {
                    $orderColumnName = $data['config']['order_column_name'] ?? '';

                    // 如果配置了排序字段名，需要更新对应的columns字段
                    if (!empty($orderColumnName)) {
                        // 重置所有字段的is_order为0
                        $table->columns()->update(['is_order' => 0]);

                        // 设置指定字段的is_order为1
                        $table->columns()->where('column_name', $orderColumnName)->update(['is_order' => 1]);
                    }
                } else {
                    // 禁用排序，重置所有字段的is_order为0
                    $table->columns()->update(['is_order' => 0]);
                }

                // 更新表基本信息
                $table->update([
                    'table_name'    => $data['basic']['table_name'] ?? $table->table_name,
                    'table_content' => $data['basic']['table_content'] ?? $table->table_content,
                    'class_name'    => $data['basic']['class_name'] ?? $table->class_name,
                    'module_name'   => $data['basic']['module_name'] ?? $table->module_name,
                    'plugin_name'   => $data['basic']['plugin_name'] ?? $table->plugin_name ?? '',
                    'edit_type'     => $data['config']['edit_type'] ?? $table->edit_type,
                    'order_type'    => $data['config']['order_type'] ?? $table->order_type,
                    'relations'     => $data['relations'] ?? $table->relations,
                    'parent_menu'   => $data['config']['parent_menu'] ?? '',
                ]);

                // 更新关联的列（除了is_delete和is_order字段）
                $columns = $data['columns'] ?? [];
                if (!empty($columns)) {
                    foreach ($columns as $columnData) {
                        $columnId = $columnData['id'] ?? null;
                        if (!$columnId) {
                            continue;
                        }
                        // 排除不应通过PUT更新的系统字段
                        unset(
                            $columnData['id'],
                            $columnData['table_id'],
                            $columnData['tenant_id'],
                            $columnData['created_at'],
                            $columnData['updated_at'],
                            $columnData['created_by'],
                            $columnData['updated_by'],
                            $columnData['deleted_at'],
                            $columnData['remark'],
                            $columnData['create_time'],
                            $columnData['update_time'],
                            $columnData['is_delete'],
                            $columnData['is_order'],
                            $columnData['updated_date'],
                            $columnData['created_date']
                        );
                        $table->columns()->where('id', $columnId)->update($columnData);
                    }
                }
            } catch (\Throwable $e) {
                throw new AdminException($e->getMessage());
            }
        });
    }

    /**
     * 删除
     *
     * @param array $data
     *
     * @throws \Throwable
     * @throws \core\exception\handler\AdminException
     */
    public function destroy(array $data): void
    {
        $this->transaction(function () use ($data) {
            try {
                /** @var GeneratorTableService $generateTableService */
                $generateTableService = Container::make(GeneratorTableService::class);
                $tables               = $generateTableService->selectList(['id' => $data]);
                foreach ($tables as $table) {
                    $table->columns()->delete(); // 删除所有关联的列
                    $table->delete(); // 删除表数据
                }
            } catch (\Throwable $e) {
                throw new AdminException($e->getMessage());
            }
        });
    }

    /**
     * 预览
     *
     * @param array $params
     *
     * @return array
     * @throws \core\exception\handler\AdminException
     */
    public function preview(array $params): array
    {
        try {
            $id         = $params['id'];
            $tableModel = $this->dao->getDetail($id);
            if (!$tableModel) {
                throw new AdminException('异常资源不存在');
            }

            /** @var GeneratorEngine $generateService */
            $generateService = Container::make(GeneratorEngine::class, ['config'=>$tableModel->toArray()]);
            return $generateService->preview();
        } catch (\Throwable $e) {
            throw new AdminException($e->getMessage());
        }
    }

    /**
     * 下载
     *
     * @param array $params
     *
     * @return array
     * @throws \core\exception\handler\AdminException
     */
    public function download(string|int $id): array
    {
        $tableModel = $this->dao->getDetail($id);
        if (!$tableModel) {
            throw new AdminException('异常资源不存在');
        }
        /** @var GeneratorEngine $generateService */
        $generateService = Container::make(GeneratorEngine::class, ['config'=>$tableModel->toArray()]);
        return $generateService->download();
    }

    /**
     * 部署
     *
     * @param array $params
     *
     * @return array
     * @throws \core\exception\handler\AdminException
     */
    public function deploy(array $params): array
    {
        try {
            $id         = $params['id'];
            $tableModel = $this->dao->getDetail($id);
            if (!$tableModel) {
                throw new AdminException('异常资源不存在');
            }
            $moduleNameRestrictions = config('core.business.generator.module_name_restrictions.reserved_names',[]);
            if(in_array($tableModel->module_name,$moduleNameRestrictions)){
                throw new AdminException('模块名称不能使用，系统保留名称：'.$tableModel->module_name);
            }
            /** @var GeneratorEngine $generateService */
            $generateService = Container::make(GeneratorEngine::class, ['config'=>$tableModel->toArray()]);
            return $generateService->deploy();
        } catch (\Throwable $e) {
            throw new AdminException($e->getMessage());
        }
    }

    private function getFieldType(string $type): string
    {
        if (str_starts_with($type, 'set') || str_starts_with($type, 'dict')) {
            $result = 'string';
        } elseif (preg_match('/(double|float|decimal|real|numeric)/is', $type)) {
            $result = 'float';
        } elseif (preg_match('/(int|serial|bit)/is', $type)) {
            $result = 'int';
        } elseif (preg_match('/bool/is', $type)) {
            $result = 'bool';
        } elseif (str_starts_with($type, 'timestamp')) {
            $result = 'timestamp';
        } elseif (str_starts_with($type, 'datetime')) {
            $result = 'datetime';
        } elseif (str_starts_with($type, 'date')) {
            $result = 'date';
        } else {
            $result = 'string';
        }
        return $result;
    }

}