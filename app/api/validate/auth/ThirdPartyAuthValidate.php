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

use app\enum\member\ThirdPartyPlatform;
use core\foundation\base\BaseValidate;

/**
 * 绑定验证器
 */
class ThirdPartyAuthValidate extends BaseValidate
{
    /**
     * 验证规则
     */
    protected array $rule = [
        'platform' => 'required|integer|checkPlatform',
        'openid' => 'required|string|max:255',
        'unionid' => 'max:255',
        'nickname' => 'max:50',
        'avatar' => 'max:255',
        'gender' => 'integer|between:0,2',
        'country' => 'max:50',
        'province' => 'max:50',
        'city' => 'max:50',
        'access_token' => 'max:255',
        'refresh_token' => 'max:255',
    ];

    /**
     * 验证消息
     */
    protected array $messages = [
        'platform.required' => '平台类型不能为空',
        'platform.integer' => '平台类型必须是整数',
        'openid.required' => 'OpenID不能为空',
        'openid.string' => 'OpenID必须是字符串',
        'openid.max' => 'OpenID不能超过255个字符',
        'unionid.max' => 'UnionID不能超过255个字符',
        'nickname.max' => '昵称不能超过50个字符',
        'avatar.max' => '头像地址不能超过255个字符',
        'gender.integer' => '性别必须是整数',
        'gender.between' => '性别只能是0-2',
        'country.max' => '国家不能超过50个字符',
        'province.max' => '省份不能超过50个字符',
        'city.max' => '城市不能超过50个字符',
        'access_token.max' => '访问令牌不能超过255个字符',
        'refresh_token.max' => '刷新令牌不能超过255个字符',
    ];

    /**
     * 验证场景
     */
    protected array $scenes = [
        'bind' => ['platform', 'openid', 'unionid', 'nickname', 'avatar', 'gender', 'country', 'province', 'city', 'access_token', 'refresh_token'],
        'qrCode' => ['platform'],
    ];

    /**
     * 检查平台类型是否有效
     */
    protected function checkPlatform($value, $rule, $data): bool
    {
        $platform = ThirdPartyPlatform::tryFrom((int)$value);
        if (!$platform) {
            $this->error = '无效的平台类型';
            return false;
        }
        return true;
    }
}
