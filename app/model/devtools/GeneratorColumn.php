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
 * Official Website: https://madong.tech
 */

namespace app\model\devtools;

use core\foundation\base\BaseModel;

/**
 * 代码生成器-字段
 *
 * @author Mr.April
 * @since  1.0
 */
class GeneratorColumn extends BaseModel
{

    protected $table = 'generate_column';

    /**
     * 指示是否自动维护时间戳
     *
     * @var bool
     */
    public $timestamps = true;

    protected $appends = ['created_date', 'updated_date'];

    protected $fillable = [
        'id',
        'table_id',
        'column_name',
        'column_comment',
        'column_type',
        'is_required',
        'is_pk',
        'is_insert',
        'is_update',
        'is_lists',
        'is_query',
        'is_search',
        'query_type',
        'view_type',
        'dict_type',
        'plugin',
        'model',
        'sort',
        'label_key',
        'value_key',
        'create_time',
        'update_time',
        'is_delete',
        'is_order',
        'validate_type',
        'tenant_id',
    ];


    protected $casts = [
        'created_by' => 'string',
        'id'         => 'string',
        'table_id'   => 'string',
        'tenant_id'  => 'string',
        'updated_by' => 'string',
    ];
}
