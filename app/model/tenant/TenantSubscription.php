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

use core\foundation\base\BasePivot;

/**
 * 租户-套餐订阅实例模型（中间表）
 * 记录租户订阅了哪个套餐、何时到期
 * 继承 BasePivot 以便在 BelongsToMany 关联中使用自定义中间表行为
 *
 * @property int    $id               订阅实例ID
 * @property int    $tenant_id        租户ID
 * @property int    $subscription_id  套餐ID
 * @property string $start_time       开始时间
 * @property string $expire_time      到期时间
 * @property string $status           状态: active/trial/expired/cancelled
 * @property bool   $auto_renew       自动续费
 * @property string $payment_status   支付状态
 * @property string $payment_time     支付时间
 * @property string $payment_method   支付方式
 * @property string $transaction_id   交易流水号
 */
class TenantSubscription extends BasePivot
{
    protected $table = 'saas_tenant_subscription';

    public $timestamps = false;
    public $incrementing = false;

    /** @var string 系统表，使用默认库连接 */
    protected $connection;

    public function __construct(array $attributes = [])
    {
        $this->connection = config('database.default');
        parent::__construct($attributes);
    }


    protected $casts = [
        'tenant_id'       => 'string',
        'subscription_id' => 'string',
    ];

    protected $fillable = [
        'tenant_id',
        'subscription_id',
    ];

    /**
     * 关联租户
     */
    public function tenant(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id', 'id');
    }

    /**
     * 关联套餐
     */
    public function subscription(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Subscription::class, 'subscription_id', 'id');
    }
}
