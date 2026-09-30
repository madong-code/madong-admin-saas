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
namespace app\api\validate\auth;

use core\foundation\base\BaseValidate;

/**
 * 登录参数验证器
 */
class LoginValidate extends BaseValidate
{
    /**
     * 验证规则
     */
    protected array $rules = [
        'username' => 'required|min:3|max:50',
        'password' => 'required|min:6|max:32',
        'captcha' => 'sometimes|max:10',
    ];

    /**
     * 验证提示信息
     */
    protected array $messages = [
        'username.required' => '用户名不能为空',
        'username.min' => '用户名长度不能少于3个字符',
        'username.max' => '用户名长度不能超过50个字符',
        'password.required' => '密码不能为空',
        'password.min' => '密码长度不能少于6个字符',
        'password.max' => '密码长度不能超过32个字符',
        'captcha.max' => '验证码长度不能超过10个字符',
    ];

    /**
     * 验证场景
     */
    protected array $scenes = [
        'login' => ['username', 'password', 'captcha'],
        'quick_login' => ['username', 'password'],
    ];
}