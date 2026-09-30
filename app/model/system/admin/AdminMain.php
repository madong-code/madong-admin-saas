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
 */

namespace app\model\system\admin;

use app\model\system\org\Dept;
use app\model\system\org\Post;
use core\foundation\base\BaseModel;

class AdminMain extends BaseModel
{
    protected $table = 'sys_admin_main';
    protected $primaryKey = 'id';

    protected $fillable = [
        'id',
        'admin_id',
        'main_dept_id',
        'main_post_id',
        'tenant_id',
    ];


    protected $casts = [
        'admin_id'     => 'string',
        'id'           => 'string',
        'main_dept_id' => 'string',
        'main_post_id' => 'string',
        'tenant_id'    => 'string',
    ];

    public function admin(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Admin::class, 'admin_id', 'id');
    }

    public function mainDept(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Dept::class, 'main_dept_id', 'id');
    }

    public function mainPost(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Post::class, 'main_post_id', 'id');
    }
}
