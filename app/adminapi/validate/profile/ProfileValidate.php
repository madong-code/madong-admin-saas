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
namespace app\adminapi\validate\profile;

use core\foundation\base\BaseValidate;

/**
 * 个人中心验证器
 */
final class ProfileValidate extends BaseValidate
{

    public function rules(): array
    {
        return [
            'real_name'        => 'required|min:2|max:50',
            'nick_name'        => 'required|min:2|max:30',
            'email'            => 'required|email',
            'mobile_phone'     => 'required|regex:/^1[3-9]\d{9}$/',
            'sex'              => 'required|in:0,1,2',
            'signed'           => 'max:255',
            'address'          => 'max:255',
            'old_password'     => 'required|min:6|max:20',
            'new_password'     => 'required|min:6|max:20',
            'confirm_password' => 'required|same:new_password',
            'avatar'           => 'required',
        ];

    }

    public function message(): array
    {
        return [
            'real_name.required'        => '真实姓名不能为空',
            'real_name.min'            => '真实姓名最少2个字符',
            'real_name.max'            => '真实姓名最多50个字符',
            'nick_name.required'        => '昵称不能为空',
            'nick_name.min'            => '昵称最少2个字符',
            'nick_name.max'            => '昵称最多30个字符',
            'email.required'            => '邮箱不能为空',
            'email.email'              => '邮箱格式不正确',
            'mobile_phone.required'     => '手机号不能为空',
            'mobile_phone.regex'       => '手机号格式不正确',
            'sex.required'              => '请选择性别',
            'sex.in'                   => '性别参数不正确',
            'signed.max'               => '个人签名最多255个字符',
            'address.max'              => '地址最多255个字符',
            'old_password.required'     => '请输入当前密码',
            'old_password.min'         => '当前密码最少6个字符',
            'old_password.max'         => '当前密码最多20个字符',
            'new_password.required'     => '请输入新密码',
            'new_password.min'         => '新密码最少6个字符',
            'new_password.max'         => '新密码最多20个字符',
            'confirm_password.required' => '请确认新密码',
            'confirm_password.same' => '两次输入的密码不一致',
            'avatar.required'           => '请上传头像',
        ];
    }

    protected array $scenes = [
        'update-profile'  => [
            'real_name',
            'nick_name',
            'email',
            'mobile_phone',
            'sex',
            'signed',
            'address',
        ],
        'update-password' => [
            'old_password',
            'new_password',
            'confirm_password',
        ],
        'update-avatar'   => ['avatar'],
    ];

}
