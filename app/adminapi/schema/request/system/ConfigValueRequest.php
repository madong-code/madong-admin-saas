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
 * Official Website: https://madong.tech
 */

namespace app\adminapi\schema\request\system;

use madong\swagger\schema\BaseResponseDTO;
use OpenApi\Attributes as OA;
use WebmanTech\DTO\Attributes\ValidationRules;

#[OA\Schema(
    title: '配置值操作',
    description: '配置值获取和更新接口的请求参数'
)]
class ConfigValueRequest extends BaseResponseDTO
{
    #[OA\Property(
        description: '配置项编码',
        type: 'string',
        example: 'site_config',
        nullable: false
    )]
    #[ValidationRules(rules: 'required|string|max:50')]
    public string $code;

    #[OA\Property(
        description: '分组编码',
        type: 'string',
        example: 'site',
        nullable: false
    )]
    #[ValidationRules(rules: 'required|string|max:50')]
    public string $group_code;

    #[OA\Property(
        description: '配置键',
        type: 'string',
        example: 'site_name',
        nullable: false
    )]
    #[ValidationRules(rules: 'required|string|max:100')]
    public string $key;

    #[OA\Property(
        description: '配置值',
        type: 'mixed',
        example: '我的网站',
        nullable: true
    )]
    #[ValidationRules(rules: 'nullable')]
    public mixed $value = null;

    #[OA\Property(
        description: '是否强制创建配置项',
        type: 'boolean',
        example: false,
        nullable: true
    )]
    #[ValidationRules(rules: 'boolean|nullable')]
    public ?bool $force_create = false;
}