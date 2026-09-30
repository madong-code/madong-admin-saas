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

namespace app\model\sync;

/**
 * 字典项同步模型
 *
 * 用于平台中央库 → 租户库之间的字典项数据同步。
 */
class SyncDictItem extends SyncModel
{
    protected $table = 'sys_dict_item';

    protected $casts = [
        'created_by'       => 'string',
        'id'               => 'string',
        'dict_id'          => 'string',
        'dict_template_id' => 'string',
        'sort'             => 'string',
        'template_id'      => 'string',
        'tenant_id'        => 'string',
        'updated_by'       => 'string',
    ];

    protected $fillable = [
        'template_id',
        'dict_template_id',
        'dict_id',
        'label',
        'value',
        'code',
        'color',
        'other_class',
        'sort',
        'enabled',
        'remark',
        'tenant_id',
        'created_at',
        'created_by',
        'updated_at',
        'updated_by',
        'deleted_at',
    ];
}
