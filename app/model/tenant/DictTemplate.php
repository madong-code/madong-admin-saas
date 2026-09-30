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

use app\model\tenant\DictItemTemplate;
use core\foundation\base\SystemModel;

/**
 * 字典模板模型
 * 由平台运营人员配置，FIELD/DB 模式新建租户时从中复制字典
 */
class DictTemplate extends SystemModel
{
    protected $table = 'saas_template_dict';

    protected $primaryKey = 'id';

    protected $appends = ['created_date', 'updated_date'];

    protected $fillable = [
        'id', 'app', 'group_code', 'name', 'code', 'sort', 'data_type',
        'description', 'enabled',
        'created_at', 'created_by', 'updated_at', 'updated_by', 'deleted_at',
    ];

    protected $casts = [
        'created_by' => 'string',
        'id'         => 'string',
        'updated_by' => 'string',
    ];

    /**
     * 模板项
     */
    public function items(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(DictItemTemplate::class, 'dict_template_id', 'id');
    }
}
