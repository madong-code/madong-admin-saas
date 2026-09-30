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
namespace app\model\tenant;

use core\foundation\base\SystemModel;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * 套餐订阅模型（套餐定义）
 *
 * @property int    $id           套餐ID
 * @property string $code         套餐代码
 * @property string $name         套餐名称
 * @property string $description  套餐描述
 * @property float  $price        价格/月
 * @property float  $price_year   价格/年
 * @property int    $max_users    最大用户数
 * @property int    $max_storage  最大存储MB
 * @property int    $max_agents   最大坐席数
 * @property int    $rate_limit   API限制/天
 * @property int    $sort         排序
 * @property bool   $is_trial     是否支持试用
 * @property int    $trial_days   试用天数
 * @property string $status       状态: active/disabled
 * @property bool   $is_default   是否默认套餐
 * @property array  $settings     扩展配置
 */
class Subscription extends SystemModel
{
    use SoftDeletes;

    protected $table = 'saas_subscription';
    protected $primaryKey = 'id';


    protected $casts = [
        'settings'    => 'array',
        'price'       => 'decimal:2',
        'price_year'  => 'decimal:2',
        'max_users'   => 'integer',
        'max_storage' => 'integer',
        'max_agents'  => 'integer',
        'rate_limit'  => 'integer',
        'sort'        => 'integer',
        'is_trial'    => 'boolean',
        'is_default'  => 'boolean',
    ];

    protected $fillable = [
        'code', 'name', 'description',
        'price', 'price_year',
        'max_users', 'max_storage', 'max_agents',
        'rate_limit', 'sort',
        'is_trial', 'trial_days',
        'status', 'is_default', 'settings',
    ];

    const STATUS_ACTIVE   = 'active';
    const STATUS_DISABLED = 'disabled';

    /**
     * 删除套餐时级联清理关联数据
     */
    public static function boot()
    {
        parent::boot();
        static::deleting(function ($model) {
            $model->permissions()->delete();
            $model->tenantSubscriptions()->delete();
        });
    }

    /**
     * 关联权限策略
     */
    public function permissions(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(SubscriptionPermission::class, 'subscription_id', 'id');
    }

    /**
     * 关联使用此套餐的租户（多对多，通过中间表 saas_tenant_subscription）
     */
    public function tenants(): BelongsToMany
    {
        return $this->belongsToMany(Tenant::class, TenantSubscription::class, 'subscription_id', 'tenant_id');
    }

    /**
     * 关联租户订阅实例（历史记录）
     */
    public function tenantSubscriptions(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(TenantSubscription::class, 'subscription_id', 'id');
    }

    /**
     * 获取关联的租户ID列表
     */
    public function getTenantIds(): array
    {
        return $this->tenantSubscriptions()->pluck('tenant_id')->toArray();
    }
}
