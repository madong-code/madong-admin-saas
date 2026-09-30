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
 * 会员积分模型
 */
class MemberPoints extends BaseModel
{

    protected $table = 'member_points';

    protected $fillable = [
        'id',
        'member_id',
        'points',
        'balance',
        'points_after',
        'type',
        'source',
        'remark',
        'operator',
        'order_id',
        'created_at',
        'create_time',
        'tenant_id',
    ];

    /**
     * 主键类型
     */
    protected $keyType = 'string';

    /**
     * 是否自增
     */
    public $incrementing = false;

    protected static function booted(): void
    {

    }
    protected $casts = [
        'balance'      => 'integer',
        'id'           => 'string',
        'member_id'    => 'string',
        'order_id'     => 'string',
        'points'       => 'integer',
        'points_after' => 'integer',
        'tenant_id'    => 'string',
        'type'         => 'integer',
    ];

    /**
     * 关联会员模型
     */
    public function member(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Member::class, 'member_id', 'id');
    }

}

