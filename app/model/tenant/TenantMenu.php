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
 * 租户独立库菜单模型（DB 模式）
 * 库隔离模式下，每个租户独立库中的菜单表
 */
class TenantMenu extends SystemModel
{
    protected $table = 'sys_tenant_menu';

    protected $primaryKey = 'id';

    protected $appends = ['created_date', 'updated_date'];

    protected $fillable = [
        'id', 'pid', 'app', 'source', 'title', 'code', 'level',
        'type', 'sort', 'path', 'component', 'redirect', 'icon',
        'is_show', 'is_link', 'link_url', 'enabled', 'open_type',
        'is_cache', 'is_sync', 'is_affix', 'is_global', 'variable',
        'methods', 'is_frame',
        'created_at', 'created_by', 'updated_at', 'updated_by', 'deleted_at',
    ];

    public function children(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(self::class, 'pid');
    }
}
