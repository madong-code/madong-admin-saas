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
namespace app\adminapi\schema\request\auth\profile;

use OpenApi\Attributes as OA;

#[OA\Schema(
    title: '个人信息更新请求',
    description: '更新当前登录用户的个人信息'
)]
final class ProfileUpdateRequest
{
    #[OA\Property(
        property: 'real_name',
        description: '真实姓名',
        type: 'string',
        minLength: 2,
        maxLength: 50,
        example: '张三'
    )]
    public string $real_name;

    #[OA\Property(
        property: 'nick_name',
        description: '昵称',
        type: 'string',
        minLength: 2,
        maxLength: 30,
        example: '小明'
    )]
    public string $nick_name;

    #[OA\Property(
        property: 'email',
        description: '邮箱',
        type: 'string',
        format: 'email',
        example: 'user@example.com'
    )]
    public string $email;

    #[OA\Property(
        property: 'mobile_phone',
        description: '手机号码',
        type: 'string',
        pattern: '^1[3-9]\\d{9}$',
        example: '13800138000'
    )]
    public string $mobile_phone;

    #[OA\Property(
        property: 'sex',
        description: '性别：0=未知，1=男，2=女',
        type: 'integer',
        enum: [0, 1, 2],
        example: 1
    )]
    public int $sex;

    #[OA\Property(
        property: 'signed',
        description: '个人签名',
        type: 'string',
        nullable: true,
        example: '专注于用户体验设计'
    )]
    public ?string $signed;

    #[OA\Property(
        property: 'address',
        description: '地址',
        type: 'string',
        nullable: true,
        example: '北京市朝阳区'
    )]
    public ?string $address;
}
