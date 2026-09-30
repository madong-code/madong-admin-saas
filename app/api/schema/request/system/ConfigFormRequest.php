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

namespace app\api\schema\request\system;

use app\schema\request\BaseFormRequest;
use OpenApi\Attributes as OA;
use WebmanTech\DTO\Attributes\ValidationRules;

#[OA\Schema(
    title: '配置表单',
    description: '配置创建和编辑接口共用的表单请求参数'
)]
class ConfigFormRequest extends BaseFormRequest
{
    #[OA\Property(
        description: '分组编码',
        type: 'string',
        example: 'site',
        nullable: false
    )]
    #[ValidationRules(rules: 'required|string|max:50')]
    public string $group_code;

    #[OA\Property(
        description: '配置编码',
        type: 'string',
        example: 'site_name',
        nullable: false
    )]
    #[ValidationRules(rules: 'required|string|max:50|unique:sys_config,code')]
    public string $code;

    #[OA\Property(
        description: '配置名称',
        type: 'string',
        example: '网站名称',
        nullable: false
    )]
    #[ValidationRules(rules: 'required|string|max:100')]
    public string $name;

    #[OA\Property(
        description: '配置内容',
        type: 'mixed',
        example: '我的网站',
        nullable: false
    )]
    #[ValidationRules(rules: 'required')]
    public mixed $content;

    #[OA\Property(
        description: '是否启用(0:禁用,1:启用)',
        type: 'integer',
        enum: [0, 1],
        example: 1,
        nullable: false
    )]
    #[ValidationRules(rules: 'required|in:0,1')]
    public int $enabled;

    #[OA\Property(
        description: '配置描述',
        type: 'string',
        example: '网站名称配置项',
        nullable: true
    )]
    #[ValidationRules(rules: 'string|max:255|nullable')]
    public ?string $description = null;

    #[OA\Property(
        description: '排序',
        type: 'integer',
        example: 1,
        nullable: true
    )]
    #[ValidationRules(rules: 'integer|min:0|nullable')]
    public ?int $sort = null;
}