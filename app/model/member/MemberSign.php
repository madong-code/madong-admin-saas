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
namespace app\model\member;

use core\foundation\base\BaseModel;

/**
 * 会员签到模型
 */
class MemberSign extends BaseModel
{
    /**
     * 数据表名称
     */
    protected $table = 'member_sign';

    /**
     * 数据表主键
     */
    protected $primaryKey = 'id';

    /**
     * 可批量赋值的字段
     */
    protected $fillable = [
        'id',
        'member_id',
        'sign_date',
        'points',
        'continuous_days',
        'is_resign',
        'device_ip',
        'device_ua',
        'created_at',
        'updated_at',
        'tenant_id',
    ];


    protected $casts = [
        'id'        => 'string',
        'member_id' => 'string',
        'tenant_id' => 'string',
        'is_resign' => 'integer',
    ];

    /**
     * 关联会员
     */
    public function member(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Member::class, 'member_id', 'id');
    }
}