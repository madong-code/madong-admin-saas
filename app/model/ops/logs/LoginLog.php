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

use app\model\system\admin\Admin;
use core\foundation\base\BaseModel;

class LoginLog extends BaseModel
{

    /**
     * 数据表主键
     *
     * @var string
     */
    protected $primaryKey = 'id';

    protected $table = 'sys_login_log';

    protected $appends = ['created_date', 'updated_date'];

    protected $fillable = [
        'id',
        'user_id',
        'app',
        'ip',
        'ip_location',
        'os',
        'browser',
        'status',
        'message',
        'login_time',
        'key',
        'created_at',
        'expires_at',
        'updated_at',
        'deleted_at',
        'remark',
        'tenant_id',
    ];


    protected $casts = [
        'id'        => 'string',
        'tenant_id' => 'string',
        'user_id'   => 'string',
    ];

    /**
     * 关联管理员
     */
    public function account(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Admin::class, 'user_id', 'id');
    }
}
