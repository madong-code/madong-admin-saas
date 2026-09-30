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
 * 字典同步模型
 *
 * 用于平台中央库 → 租户库之间的字典数据同步。
 * 无 TenantScope、无事件绑定、无自动字段填充，纯数据操作。
 */
class SyncDict extends SyncModel
{
    protected $table = 'sys_dict';

    protected $casts = [
        'created_by'  => 'string',
        'id'          => 'string',
        'sort'        => 'string',
        'template_id' => 'string',
        'tenant_id'   => 'string',
        'updated_by'  => 'string',
    ];

    protected $fillable = [
        'template_id',
        'group_code',
        'name',
        'code',
        'sort',
        'data_type',
        'description',
        'enabled',
        'tenant_id',
        'created_at',
        'created_by',
        'updated_at',
        'updated_by',
        'deleted_at',
    ];
}
