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

namespace app\model\tenant;

use core\foundation\base\SystemModel;

/**
 * 字典模板项模型
 */
class DictItemTemplate extends SystemModel
{
    protected $table = 'saas_template_dict_item';

    protected $primaryKey = 'id';

    protected $appends = ['created_date', 'updated_date'];

    protected $fillable = [
        'id', 'dict_template_id', 'label', 'value', 'code', 'color',
        'other_class', 'sort', 'enabled', 'remark',
        'created_at', 'created_by', 'updated_at', 'updated_by', 'deleted_at',
    ];

    protected $casts = [
        'created_by'      => 'string',
        'id'              => 'string',
        'dict_template_id' => 'string',
        'updated_by'      => 'string',
    ];
}
