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
namespace app\platform\validate\auth;

use app\platform\validate\BaseValidate;

/**
 * 登录参数验证器
 */
class LoginValidate extends BaseValidate
{
    protected array $rules = [
        'user_name' => 'required|string|min:2|max:50',
        'password' => 'required|string|min:6|max:32',
        'captcha' => 'nullable|string|max:10',
    ];

    protected array $messages = [
        'user_name.required' => '用户名不能为空',
        'user_name.string' => '用户名必须是字符串',
        'user_name.min' => '用户名长度不能少于2个字符',
        'user_name.max' => '用户名长度不能超过50个字符',
        'password.required' => '密码不能为空',
        'password.string' => '密码必须是字符串',
        'password.min' => '密码长度不能少于6个字符',
        'password.max' => '密码长度不能超过32个字符',
        'captcha.string' => '验证码必须是字符串',
        'captcha.max' => '验证码长度不能超过10个字符',
    ];

    protected array $scenes = [
        'login' => ['user_name', 'password'],
    ];

    protected function sceneLogin(): void
    {
        $this->only = ['user_name', 'password'];
    }
}
