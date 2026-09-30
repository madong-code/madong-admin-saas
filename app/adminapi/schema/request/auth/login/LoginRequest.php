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

namespace app\adminapi\schema\request\auth\login;

use madong\swagger\schema\BaseResponseDTO;
use OpenApi\Attributes as OA;
use WebmanTech\DTO\Attributes\ValidationRules;

#[OA\Schema(
    title: '登录请求',
    description: '登录请求参数'
)]
class LoginRequest extends BaseResponseDTO
{

    #[OA\Property(
        description: '用户名',
        type: 'string',
        example: 'admin',
        nullable: true,
    )]
    #[ValidationRules(rules: 'sometimes|string|max:50')]
    public ?string $user_name = null;

    #[OA\Property(
        description: '密码',
        type: 'string',
        example: '123456',
        nullable: true,
    )]
    #[ValidationRules(rules: 'sometimes|string')]
    public ?string $password = null;

    #[OA\Property(
        description: '手机号码',
        type: 'string',
        example: '18888888888',
        nullable: true
    )]
    #[ValidationRules(rules: 'sometimes|mobile')]
    public ?string $mobile_phone = null;

    #[OA\Property(
        description: '验证码',
        type: 'string',
        example: '1234',
        nullable: true
    )]
    #[ValidationRules(rules: 'sometimes|string|length:4,6')]
    public ?string $code = null;

    #[OA\Property(
        description: '验证码UUID',
        type: 'string',
        example: 'a3bb6c8c-9b12-467b-acbf-3a3c2ecd2d2f',
        nullable: true
    )]
    #[ValidationRules(rules: 'sometimes|uuid')]
    public ?string $uuid = null;

    #[OA\Property(
        description: '登录类型',
        type: 'string',
        default: 'admin',
        example: 'admin'
    )]
    #[ValidationRules(rules: 'default:admin|string|max:20')]
    public string $type = 'admin';

    #[OA\Property(
        description: '授权类型',
        type: 'string',
        default: 'default',
        enum: ['default', 'sms', 'refresh_token'],
        example: 'default'
    )]
    #[ValidationRules(rules: 'default:default|in:default,sms,refresh_token')]
    public string $grant_type = 'default';

    #[OA\Property(
        description: '公钥ID',
        type: 'string',
        example: '8119456a6fa4dfe32d01d84fc195ad23',
        nullable: true,
    )]
    #[ValidationRules(rules: 'sometimes|string|max:100')]
    public ?string $key_id = null;

}
