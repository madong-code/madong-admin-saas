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
 * 平台运营端菜单模型
 * 与菜单模板共用 saas_template_menu 表，通过 app='platform' 区分
 * 供 frontend/apps/platform 使用，与租户完全无关
 */
class PlatformMenu extends SystemModel
{
    protected $table = 'saas_template_menu';

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


    protected $casts = [
        'created_by' => 'string',
        'id'         => 'string',
        'pid'        => 'string',
        'updated_by' => 'string',
    ];    public function children(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(self::class, 'pid');
    }
}
