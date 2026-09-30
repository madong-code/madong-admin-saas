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
namespace app\platform\validate\permission;

use app\platform\validate\BaseValidate;

/**
 * 权限管理验证器
 */
class PermissionValidate extends BaseValidate
{
    protected array $rules = [
        'id' => 'required|string|max:64',
        'name' => 'required|string|min:2|max:100',
        'code' => 'required|string|min:2|max:100',
        'parent_id' => 'nullable|string|max:64',
        'type' => 'required|integer|in:1,2,3,4',
        'path' => 'nullable|string|max:255',
        'icon' => 'nullable|string|max:100',
        'sort' => 'nullable|integer|min:0',
        'enabled' => 'nullable|integer|in:0,1',
        'permission' => 'nullable|string|max:100',
    ];

    protected array $messages = [
        'id.required' => '权限ID不能为空',
        'id.string' => '权限ID必须是字符串',
        'id.max' => '权限ID长度不能超过64字符',
        'name.required' => '权限名称不能为空',
        'name.string' => '权限名称必须是字符串',
        'name.min' => '权限名称长度不能少于2个字符',
        'name.max' => '权限名称长度不能超过100个字符',
        'code.required' => '权限编码不能为空',
        'code.string' => '权限编码必须是字符串',
        'code.min' => '权限编码长度不能少于2个字符',
        'code.max' => '权限编码长度不能超过100个字符',
        'parent_id.string' => '父级ID必须是字符串',
        'parent_id.max' => '父级ID长度不能超过64字符',
        'type.required' => '类型不能为空',
        'type.integer' => '类型必须是整数',
        'type.in' => '类型值只能是1、2、3或4',
        'path.string' => '路径必须是字符串',
        'path.max' => '路径长度不能超过255字符',
        'icon.string' => '图标必须是字符串',
        'icon.max' => '图标长度不能超过100字符',
        'sort.integer' => '排序必须是整数',
        'sort.min' => '排序最小为0',
        'enabled.integer' => '状态必须是整数',
        'enabled.in' => '状态只能是0或1',
        'permission.string' => '权限标识必须是字符串',
        'permission.max' => '权限标识长度不能超过100字符',
    ];

    protected array $scenes = [
        'create' => ['name', 'code', 'parent_id', 'type', 'path', 'icon', 'sort', 'enabled', 'permission'],
        'update' => ['id', 'name', 'code', 'parent_id', 'type', 'path', 'icon', 'sort', 'enabled', 'permission'],
        'status' => ['id', 'enabled'],
    ];
}
