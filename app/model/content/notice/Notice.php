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
 * Official Website: https://madong.tech
 */

namespace app\model\content\notice;

use core\foundation\base\BaseModel;

/**
 * 系统公告
 *
 * @author Mr.April
 * @since  1.0
 */
class Notice extends BaseModel
{

    protected $table = 'sys_notice';

    /**
     * 指示是否自动维护时间戳
     *
     * @var bool
     */
    public $timestamps = false;

    protected $appends = ['created_date', 'updated_date'];

    protected $fillable = [
        'id',
        'message_id',
        'title',
        'type',
        'content',
        'enabled',
        'uuid',
        'created_dept',
        'created_by',
        'created_at',
        'updated_by',
        'updated_at',
        'remark',
        'tenant_id',
    ];


    protected $casts = [
        'created_by'   => 'string',
        'created_dept' => 'string',
        'id'           => 'string',
        'message_id'   => 'string',
        'tenant_id'    => 'string',
        'updated_by'   => 'string',
    ];
}
