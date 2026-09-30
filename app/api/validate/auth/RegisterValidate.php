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

use app\model\member\Member;
use core\foundation\base\BaseValidate;
use Illuminate\Validation\Rule;

/**
 * 用户注册参数验证器
 */
class RegisterValidate extends BaseValidate
{
    /**
     * 验证规则 - 使用方法定义，支持动态规则
     */
    public function rules(): array
    {
        // 获取路由参数ID（更新场景时用于排除唯一验证）
        $id = request()->route->param('id');

        return [
            'username' => [
                'required',
                'min:3',
                'max:50',
                'alpha_dash',
                Rule::unique(Member::class, 'username')->ignore($id ?? null, 'id'),
            ],
            'password' => 'required|min:6|max:32|confirmed:confirm_password',
            'email'    => [
                'required',
                'email',
                Rule::unique(Member::class, 'email')->ignore($id ?? null, 'id'),
            ],
            'mobile'   => [
                'sometimes',
                'regex:/^1[3-9]\d{9}$/',
                Rule::unique(Member::class, 'mobile')->ignore($id ?? null, 'id'),
            ],
        ];
    }

    /**
     * 验证提示信息
     */
    protected array $messages = [
        'username.required'         => '用户名不能为空',
        'username.min'              => '用户名长度不能少于3个字符',
        'username.max'              => '用户名长度不能超过50个字符',
        'username.alpha_dash'       => '用户名只能包含字母、数字、下划线和破折号',
        'username.unique'           => '用户名已存在',
        'password.required'         => '密码不能为空',
        'password.min'              => '密码长度不能少于6个字符',
        'password.max'              => '密码长度不能超过32个字符',
        'password.confirmed' => '两次输入的密码不一致',
        'email.email'               => '邮箱格式不正确',
        'email.unique'              => '邮箱已存在',
        'mobile.regex'              => '手机号格式不正确',
        'mobile.unique'             => '手机号已存在',
    ];

    /**
     * 验证场景
     */
    protected array $scenes = [
        'register'       => ['username', 'password', 'email', 'mobile'],
        'quick_register' => ['username', 'password'],
    ];
}
