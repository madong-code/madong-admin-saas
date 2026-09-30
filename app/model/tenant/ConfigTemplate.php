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
 * 配置模板模型
 * 由平台运营人员配置，FIELD/DB 模式新建租户或同步时复制到租户的 sys_config 表。
 * 所有启用的配置模板都会同步到租户。
 */
class ConfigTemplate extends SystemModel
{
    protected $table = 'saas_template_config';

    protected $primaryKey = 'id';

    protected $appends = ['created_date', 'updated_date'];

    protected $fillable = [
        'id',
        'group_code',
        'code',
        'name',
        'content',
        'is_sys',
        'enabled',
        'sort',
        'remark',
        'created_at',
        'created_by',
        'updated_at',
        'updated_by',
        'deleted_at',
    ];

    protected $casts = [
        'id'         => 'string',
    ];
}
