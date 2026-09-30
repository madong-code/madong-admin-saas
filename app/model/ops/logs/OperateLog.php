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
 * Official Website: http://www.madong.cn
 */

namespace app\model\ops\logs;

use core\foundation\base\BaseModel;

class OperateLog extends BaseModel
{

    /**
     * 数据表主键
     *
     * @var string
     */
    protected $primaryKey = 'id';

    protected $table = 'sys_operate_log';

    protected $appends = ['created_date', 'updated_date'];

    protected $fillable = [
        'id',
        'name',
        'app',
        'ip',
        'ip_location',
        'browser',
        'os',
        'url',
        'class_name',
        'action',
        'method',
        'param',
        'result',
        'created_at',
        'updated_at',
        'user_name',
        'tenant_id',
    ];


    protected $casts = [
        'id'        => 'string',
        'param'     => 'array',
        'result'    => 'array',
        'tenant_id' => 'string',
    ];

}
