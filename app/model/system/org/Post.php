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

namespace app\model\system\org;

use core\foundation\base\BaseModel;

/**
 * 岗位模型
 *
 * @author Mr.April
 * @since  1.0
 */
class Post extends BaseModel
{

    /**
     * 数据表主键
     *
     * @var string
     */
    protected $primaryKey = 'id';

    protected $table = 'sys_post';

    protected $appends = ['created_date', 'updated_date'];

    protected $fillable = [
        'id',
        'dept_id',
        'code',
        'name',
        'sort',
        'enabled',
        'created_by',
        'updated_by',
        'created_at',
        'updated_at',
        'deleted_at',
        'remark',
        'tenant_id',
    ];


    protected $casts = [
        'created_by' => 'string',
        'dept_id'    => 'string',
        'id'         => 'string',
        'tenant_id'  => 'string',
        'updated_by' => 'string',
    ];    public function dept(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Dept::class,'dept_id','id');
    }

}
