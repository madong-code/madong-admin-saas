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

use app\model\system\menu\Menu;
use core\foundation\base\SystemPivot;

/**
 * 套餐-权限关联模型（中间表）
 *
 * @property int $subscription_id 套餐ID
 * @property int $permission_id   权限ID
 */
class SubscriptionPermission extends SystemPivot
{
    /** @var string 平台级系统中间表 */
    protected $table = 'saas_subscription_permission';

    protected $casts = [
        'subscription_id' => 'string',
        'permission_id'   => 'string',
    ];

    protected $fillable = [
        'subscription_id',
        'permission_id',
    ];

    /**
     * 关联套餐
     */
    public function subscription(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Subscription::class, 'subscription_id', 'id');
    }

    /**
     * 关联权限定义
     */
    public function permission(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Permission::class, 'permission_id', 'id');
    }

    /**
     * 关联系统菜单（permission_id 实际存储的是 sys_menu 的 ID）
     */
    public function menu(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Menu::class, 'permission_id', 'id');
    }
}
