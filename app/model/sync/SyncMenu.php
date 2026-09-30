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
 * 菜单同步模型
 *
 * 用于平台中央库 → 租户库之间的菜单数据同步。
 * 无 TenantScope、无事件绑定、无自动字段填充，纯数据操作。
 */
class SyncMenu extends SyncModel
{
    protected $table = 'sys_menu';

    protected $casts = [
        'created_by'  => 'string',
        'id'          => 'string',
        'pid'         => 'string',
        'sort'        => 'string',
        'template_id' => 'string',
        'tenant_id'   => 'string',
        'updated_by'  => 'string',
    ];

    protected $fillable = [
        'pid',
        'template_id',
        'app',
        'title',
        'code',
        'level',
        'type',
        'sort',
        'path',
        'component',
        'redirect',
        'icon',
        'is_show',
        'is_link',
        'link_url',
        'open_type',
        'is_cache',
        'is_sync',
        'is_affix',
        'variable',
        'methods',
        'source',
        'platform',
        'tenant_id',
        'created_at',
        'created_by',
        'updated_at',
        'updated_by',
        'deleted_at',
    ];
}
