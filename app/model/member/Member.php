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
use app\enum\system\Sex;
use core\foundation\base\BaseModel;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * 会员模型
 */
class Member extends BaseModel
{

    /**
     * 启用软删除
     */
    use SoftDeletes;

    /**
     * 数据表名称
     */
    protected $table = 'member';

    /**
     * 数据表主键
     */
    protected $primaryKey = 'id';

    protected static function booted(): void
    {

    }

    /**
     * 可批量赋值的字段
     */
    protected $fillable = [
        'id',
        'username',
        'email',
        'phone',
        'password',
        'nickname',
        'avatar',
        'level_id',
        'points',
        'balance',
        'gender',
        'birthday',
        'last_login_time',
        'last_login_ip',
        'login_count',
        'enabled',
        'created_at',
        'updated_at',
        'deleted_at',
        'bio',
        'tenant_id',
    ];


    protected $casts = [
        'id'        => 'string',
        'level_id'  => 'string',
        'tenant_id' => 'string',
    ];

    /**
     * 隐藏字段
     */
    protected $hidden = [
        'password',
    ];

    /**
     * 追加字段
     */
    protected $appends = [
        'gender_text',
        'enabled_text',
    ];

    /**
     * 获取性别文本
     */
    public function getGenderTextAttribute(): string
    {
        return Sex::tryFrom($this->gender)?->label() ?? '未知';
    }

    /**
     * 获取状态文本
     */
    public function getEnabledTextAttribute(): string
    {
        return EnabledStatus::tryFrom($this->enabled)?->label() ?? '未知';
    }

    /**
     * 关联会员等级
     */
    public function level(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(MemberLevel::class, 'level_id', 'id');
    }

    /**
     * 关联会员标签
     */
    public function tags(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(MemberTag::class, MemberTagRelation::class, 'member_id', 'tag_id');
    }

    /**
     * 关联签到记录
     */
    public function signRecords(): Member|\Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(MemberSign::class, 'member_id', 'id');
    }

    /**
     * 关联第三方登录
     */
    public function thirdParties(): Member|\Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(MemberThirdParty::class, 'member_id', 'id');
    }

    /**
     * 关联收货地址
     */
    public function addresses(): Member|\Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(MemberAddress::class, 'member_id', 'id');
    }

    /**
     * 关联提现账号
     */
    public function withdrawAccounts(): Member|\Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(MemberWithdrawAccount::class, 'member_id', 'id');
    }

    /**
     * 关联提现记录
     */
    public function withdraws(): Member|\Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(MemberWithdraw::class, 'member_id', 'id');
    }

    /**
     * 关联账单记录
     */
    public function bills(): Member|\Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(MemberBill::class, 'member_id', 'id');
    }

    /**
     * 关联积分记录
     */
    public function pointsRecords(): Member|\Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(MemberPoints::class, 'member_id', 'id');
    }

    /**
     * 关联分组
     */
    public function groups(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(MemberGroup::class, 'member_group_relation', 'member_id', 'group_id');
    }

    /**
     * 设置密码
     */
    public function setPasswordAttribute($value): void
    {
        $this->attributes['password'] = password_hash($value, PASSWORD_DEFAULT);
    }

    /**
     * 设置生日
     */
    public function setBirthdayAttribute($value): void
    {
        if (empty($value)) {
            $this->attributes['birthday'] = null;
            return;
        }

        if (is_numeric($value)) {
            $this->attributes['birthday'] = $value;
            return;
        }

        try {
            $carbon = \Carbon\Carbon::parse($value);
            $this->attributes['birthday'] = $carbon->timestamp;
        } catch (\Exception $e) {
            $this->attributes['birthday'] = null;
        }
    }

    /**
     * 获取生日
     */
    public function getBirthdayAttribute($value): ?string
    {
        if (empty($value)) {
            return null;
        }

        try {
            return \Carbon\Carbon::createFromTimestamp($value)->toIso8601String();
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * 验证密码
     */
    public function verifyPassword($password): bool
    {
        return password_verify($password, $this->password);
    }
}
