<?php
declare(strict_types=1);

namespace app\model\tenant;

use core\foundation\base\SystemModel;

/**
 * 前端菜单模板模型
 * 由平台运营人员配置，FIELD/DB 模式新建租户时复制到租户的 web_menu 表。
 * 所有启用的前端菜单模板都会同步到租户。
 */
class WebMenuTemplate extends SystemModel
{
    protected $table = 'saas_template_web_menu';

    protected $primaryKey = 'id';

    protected $appends = ['created_date', 'updated_date'];

    protected $fillable = [
        'id', 'pid', 'app', 'category', 'source', 'code', 'is_public', 'is_no_auth',
        'name', 'url', 'icon', 'level', 'type', 'sort', 'target', 'extra', 'is_show', 'enabled',
        'created_at', 'created_by', 'updated_at', 'updated_by', 'deleted_at',
    ];

    protected $casts = [
        'created_by' => 'string',
        'id'         => 'string',
        'pid'        => 'string',
        'updated_by' => 'string',
        'category'   => 'integer',
    ];
}
