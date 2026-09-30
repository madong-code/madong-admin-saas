<?php
declare(strict_types=1);

namespace app\model\sync;

/**
 * 前端菜单同步模型
 *
 * 用于平台中央库 → 租户库之间的 web_menu 数据同步。
 * 无租户作用域、无事件绑定、无自动字段填充，纯数据操作。
 */
class SyncWebMenu extends SyncModel
{
    protected $table = 'web_menu';

    protected $casts = [
        'created_by'  => 'string',
        'id'          => 'string',
        'pid'         => 'string',
        'template_id' => 'string',
        'tenant_id'   => 'string',
        'updated_by'  => 'string',
    ];

    protected $fillable = [
        'pid',
        'template_id',
        'tenant_id',
        'app',
        'category',
        'source',
        'code',
        'is_public',
        'is_no_auth',
        'name',
        'url',
        'icon',
        'level',
        'type',
        'sort',
        'target',
        'extra',
        'is_show',
        'enabled',
        'created_at',
        'created_by',
        'updated_at',
        'updated_by',
        'deleted_at',
    ];
}
