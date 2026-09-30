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
namespace app\platform\validate\ops\db;

use core\foundation\base\BaseValidate;

/**
 * 数据源设置验证器
 */
class DbSettingValidate extends BaseValidate
{
    protected array $rules = [
        'id'          => 'required|string',
        'name'        => 'required|string|min:2|max:100',
        'driver'      => 'required|string|in:mysql,pgsql,sqlite,sqlsrv',
        'host'        => 'required|string|max:255',
        'port'        => 'required|integer|min:1|max:65535',
        'database'    => 'required|string|max:100',
        'username'    => 'required|string|max:100',
        'password'    => 'nullable|string|max:255',
        'charset'     => 'nullable|string|max:20',
        'prefix'      => 'nullable|string|max:50',
        'enabled'     => 'nullable|integer|in:0,1',
        'description' => 'nullable|string|max:500',
    ];

    protected array $messages = [
        'id.required'        => '数据源ID不能为空',
        'id.string'         => '数据源ID必须是整数',
        'name.required'      => '数据源名称不能为空',
        'name.string'        => '数据源名称必须是字符串',
        'name.min'           => '数据源名称长度不能少于2个字符',
        'name.max'           => '数据源名称长度不能超过100个字符',
        'driver.required'    => '数据库驱动不能为空',
        'driver.string'      => '数据库驱动必须是字符串',
        'driver.in'          => '数据库驱动只能是mysql、pgsql、sqlite或sqlsrv',
        'host.required'      => '数据库主机不能为空',
        'host.string'        => '数据库主机必须是字符串',
        'host.max'           => '数据库主机长度不能超过255字符',
        'port.required'      => '数据库端口不能为空',
        'port.integer'       => '数据库端口必须是整数',
        'port.min'           => '数据库端口最小为1',
        'port.max'           => '数据库端口最大为65535',
        'database.required'  => '数据库名称不能为空',
        'database.string'    => '数据库名称必须是字符串',
        'database.max'       => '数据库名称长度不能超过100字符',
        'username.required'  => '数据库用户名不能为空',
        'username.string'    => '数据库用户名必须是字符串',
        'username.max'       => '数据库用户名长度不能超过100字符',
        'password.string'    => '数据库密码必须是字符串',
        'password.max'       => '数据库密码长度不能超过255字符',
        'charset.string'     => '字符集必须是字符串',
        'charset.max'        => '字符集长度不能超过20字符',
        'prefix.string'      => '表前缀必须是字符串',
        'prefix.max'         => '表前缀长度不能超过50字符',
        'enabled.integer'    => '状态必须是整数',
        'enabled.in'         => '状态只能是0或1',
        'description.string' => '描述必须是字符串',
        'description.max'    => '描述长度不能超过500字符',
    ];

    protected array $scenes = [
        'create' => ['name', 'driver', 'host', 'port', 'database', 'username', 'password', 'charset', 'prefix', 'enabled', 'description'],
        'update' => ['id', 'name', 'driver', 'host', 'port', 'database', 'username', 'password', 'charset', 'prefix', 'enabled', 'description'],
        'status' => ['id', 'enabled'],
    ];
}
