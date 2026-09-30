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

namespace app\adminapi\schema\request\auth;

use madong\swagger\schema\BaseRequestDTO;
use OpenApi\Attributes as OA;
use WebmanTech\DTO\Attributes\ValidationRules;

#[OA\Schema(
    title: '第三方登录请求',
    description: '第三方登录请求参数'
)]
class ThirdPartyLoginRequest extends BaseRequestDTO
{
    #[OA\Property(
        description: '账套',
        type: 'string',
        example: '669e3705b3d7de'
    )]
    #[ValidationRules(rules: 'required|string|max:50')]
    public string $acct_id;

    #[OA\Property(
        description: '授权应用ID',
        type: 'string',
        example: '285191_1fbo28uL6prUQ90J5YTP2dSL6vx71tqv'
    )]
    #[ValidationRules(rules: 'required|string|max:100')]
    public string $app_id;

    #[OA\Property(
        description: '应用秘钥',
        type: 'string',
        example: '8439b36501df4f6185f521781d6b7328'
    )]
    #[ValidationRules(rules: 'required|string|max:100')]
    public string $app_secret;

    #[OA\Property(
        description: '授权登录用户名',
        type: 'string',
        example: 'admin'
    )]
    #[ValidationRules(rules: 'required|string|max:50')]
    public string $user_name;
}
