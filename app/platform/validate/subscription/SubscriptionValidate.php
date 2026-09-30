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
namespace app\platform\validate\subscription;

use core\foundation\base\BaseValidate;

/**
 * 套餐订阅验证器
 */
class SubscriptionValidate extends BaseValidate
{
    protected array $rules = [
        'id'          => 'required|string|max:64',
        'code'        => 'nullable|string|max:50',
        'name'        => 'required|string|max:100',
        'status'      => 'required|string|in:active,disabled',
        'price'       => 'nullable|numeric|min:0',
        'max_users'   => 'nullable|integer|min:0',
        'max_storage' => 'nullable|integer|min:0',
        'permissions' => 'nullable|array',
        'ids'  => 'nullable|array',
    ];

    protected array $messages = [
        'id.required'          => '套餐ID不能为空',
        'id.string'            => '套餐ID必须是字符串',
        'id.max'               => '套餐ID长度不能超过64字符',
        'name.required'        => '套餐名称不能为空',
        'name.string'          => '套餐名称必须是字符串',
        'name.max'             => '套餐名称长度不能超过100字符',
        'status.required'      => '状态不能为空',
        'status.string'        => '状态必须是字符串',
        'status.in'            => '状态值不正确',
        'price.numeric'        => '价格必须是数字',
        'price.min'            => '价格不能小于0',
        'permissions.array'    => '权限策略格式不正确',
        'ids.array'            => '租户ID列表格式不正确',
    ];

    protected array $scenes = [
        'store'      => ['name', 'status', 'price'],
        'update'     => ['id', 'name', 'status', 'price'],
        'status'     => ['id', 'status'],
        'authorize'  => ['id', 'ids'],
        'bindTenant' => ['id', 'ids'],
    ];
}
