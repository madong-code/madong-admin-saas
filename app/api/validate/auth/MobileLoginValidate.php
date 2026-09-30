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
 * 手机验证码登录参数验证器
 */
class MobileLoginValidate extends BaseValidate
{
    /**
     * 验证规则
     */
    protected array $rules = [
        'mobile' => 'required|regex:/^1[3-9]\d{9}$/',
        'code' => 'required|digits_between:4,6',
    ];

    /**
     * 验证提示信息
     */
    protected array $messages = [
        'mobile.required' => '手机号不能为空',
        'mobile.regex' => '手机号格式不正确',
        'code.required' => '验证码不能为空',
        'code.digits_between' => '验证码长度必须为4-6位',
    ];

    /**
     * 验证场景
     */
    protected array $scenes = [
        'login' => ['mobile', 'code'],
    ];
}