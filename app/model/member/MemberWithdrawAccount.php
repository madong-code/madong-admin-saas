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
use app\enum\member\WithdrawAccountType;
use core\foundation\base\BaseModel;

/**
 * 会员提现账号模型
 */
class MemberWithdrawAccount extends BaseModel
{
    /**
     * 数据表名称
     */
    protected $table = 'member_withdraw_account';

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
        'type',
        'bank_name',
        'account_name',
        'account_number',
        'branch_name',
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
        'type_text',
        'is_default_text',
        'enabled_text',
    ];

    /**
     * 获取类型文本
     */
    public function getTypeTextAttribute(): string
    {
        return WithdrawAccountType::tryFrom($this->type)?->label() ?? '未知';
    }

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
     * 关联会员
     */
    public function member(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Member::class, 'member_id', 'id');
    }

    /**
     * 关联提现记录
     */
    public function withdraws(): MemberWithdrawAccount|\Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(MemberWithdraw::class, 'account_id', 'id');
    }
}
