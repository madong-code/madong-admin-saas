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

/**
 * 权限定义模型
 *
 * @property int    $id              权限ID
 * @property string $permission_key  权限标识
 * @property string $permission_name 权限名称
 * @property string $permission_type 权限类型: module/field/api/feature
 * @property array  $config          权限配置JSON
 * @property int    $sort            排序
 */
class Permission extends SystemModel
{
    protected $table = 'saas_permission';
    protected $primaryKey = 'id';

    public $timestamps = false;


    protected $casts = [
        'sort'   => 'integer',
        'config' => 'json',
    ];

    protected $fillable = [
        'permission_key',
        'permission_name',
        'permission_type',
        'config',
        'sort',
    ];

    // 权限类型常量
    const TYPE_MODULE  = 'module';
    const TYPE_FIELD   = 'field';
    const TYPE_API     = 'api';
    const TYPE_FEATURE = 'feature';

    /**
     * 关联套餐（多对多）
     */
    public function subscriptions(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(
            Subscription::class,
            'saas_subscription_permission',
            'permission_id',
            'subscription_id'
        );
    }
}
