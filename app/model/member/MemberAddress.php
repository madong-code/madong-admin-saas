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

use app\enum\common\EnabledStatus;
use core\foundation\base\BaseModel;

/**
 * 会员收货地址模型
 */
class MemberAddress extends BaseModel
{
    /**
     * 数据表名称
     */
    protected $table = 'member_address';

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
        'name',
        'phone',
        'province',
        'city',
        'district',
        'address',
        'zipcode',
        'is_default',
        'enabled',
        'created_at',
        'updated_at',
        'deleted_at',
        'tenant_id',
    ];


    protected $casts = [
        'id'        => 'string',
        'member_id' => 'string',
        'tenant_id' => 'string',
    ];

    /**
     * 追加字段
     */
    protected $appends = [
        'is_default_text',
        'enabled_text',
        'full_address',
    ];

    /**
     * 获取默认状态文本
     */
    public function getIsDefaultTextAttribute(): string
    {
        return $this->is_default == 1 ? '默认' : '非默认';
    }

    /**
     * 获取状态文本
     */
    public function getEnabledTextAttribute(): string
    {
        return EnabledStatus::tryFrom($this->enabled)?->label() ?? '未知';
    }

    /**
     * 获取完整地址
     */
    public function getFullAddressAttribute(): string
    {
        return implode('', [
            $this->province,
            $this->city,
            $this->district,
            $this->address,
        ]);
    }

    /**
     * 关联会员
     */
    public function member(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Member::class, 'member_id', 'id');
    }
}
