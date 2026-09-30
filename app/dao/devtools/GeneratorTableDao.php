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

namespace app\dao\devtools;

use app\model\devtools\GeneratorTable;
use core\foundation\base\BaseDao;

/**
 * 代码生成器-表数据访问层
 *
 * @author Mr.April
 * @since  1.0
 */
class GeneratorTableDao extends BaseDao
{

    protected function setModel(): string
    {
        return GeneratorTable::class;
    }

    /**
     * 获取代码生成器-表详情
     *
     * @param $id
     *
     * @return \app\model\generator\GeneratorTable|null
     * @throws \Exception
     */
    public function getDetail($id): GeneratorTable|null
    {
        $model = $this->get($id, ['*'], ['columns' => function ($query) {
            $query->orderBy('sort', 'asc')
                ->orderBy('created_at', 'asc');
        }]);
        if (!empty($model)) {
            $basic = [
                'table_name'    => $model->table_name ?? '',
                'table_content' => $model->table_content ?? '',
                'plugin_name'   => $model->plugin_name ?? '',
                'module_name'   => $model->module_name ?? '',
                'class_name'    => $model->class_name ?? '',
            ];

            $softDeleteField  = null;
            $softDeleteStatus = 0;

            $orderField = null;
            $orderType  = 0; // 0=没有排序, 1=正序, 2=倒序

            if (!empty($model->columns)) {
                foreach ($model->columns as $column) {
                    // 检查is_delete字段是否为1
                    if (isset($column->is_delete) && $column->is_delete == 1) {
                        $softDeleteField  = $column->column_name ?? '';
                        $softDeleteStatus = 1; // 启用软删除
                    }

                    // 检查is_order字段是否为1
                    if (isset($column->is_order) && $column->is_order == 1) {
                        $orderField = $column->column_name ?? '';
                        $orderType = $model->order_type ?? 0;
                        break;
                    }
                }
            }

            // 如果配置中明确指定了删除字段名，则优先使用配置
            if (!empty($model->delete_column_name)) {
                $softDeleteField  = $model->delete_column_name;
                $softDeleteStatus = 1;
            }

            // 如果配置中明确指定了排序字段名，则优先使用配置
            if (!empty($model->order_column_name)) {
                $orderField = $model->order_column_name;
                $orderType  = $model->order_type ?? 0;
            }

            $relations = [];
            if (!empty($model->relations)) {
                // 尝试解析JSON格式的relations
                $decodedRelations = json_decode($model->relations, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decodedRelations)) {
                    $relations = $decodedRelations;
                } else {
                    // 如果不是JSON格式，尝试其他解析方式或返回空数组
                    $relations = [];
                }
            }

            $config = [
                'is_delete'          => $softDeleteStatus,
                'delete_column_name' => $softDeleteField ?? '',
                'edit_type'          => $model->edit_type ?? 0,
                'order_column_name'  => $orderField ?? '',
                'order_type'         => $orderType,
                'parent_menu'        => $model->parent_menu ?? '',
                'plugin_name'        => $model->plugin_name ?? '',
                'relations'          => $relations, // 添加转换后的relations数组
            ];
            $model->setAttribute('basic', $basic);
            $model->setAttribute('config', $config);
            $model->setAttribute('relations', $relations);
        }
        return $model;
    }


}
