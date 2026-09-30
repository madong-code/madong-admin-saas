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
    title: '密码修改请求',
    description: '修改当前登录用户的密码'
)]
final class PasswordUpdateRequest
{
    #[OA\Property(
        property: 'old_password',
        description: '当前密码',
        type: 'string',
        minLength: 6,
        maxLength: 20,
        example: 'old123456'
    )]
    public string $old_password;

    #[OA\Property(
        property: 'new_password',
        description: '新密码',
        type: 'string',
        minLength: 6,
        maxLength: 20,
        example: 'new123456'
    )]
    public string $new_password;

    #[OA\Property(
        property: 'confirm_password',
        description: '确认新密码',
        type: 'string',
        minLength: 6,
        maxLength: 20,
        example: 'new123456'
    )]
    public string $confirm_password;
}
