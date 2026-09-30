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

use app\schema\request\BaseQueryRequest;
use OpenApi\Attributes as OA;
use WebmanTech\DTO\Attributes\ValidationRules;

#[OA\Schema(
    title: '配置查询',
    description: '配置查询接口的请求参数'
)]
class ConfigQueryRequest extends BaseQueryRequest
{
    #[OA\Property(
        description: '分组编码',
        type: 'string',
        example: 'site',
        nullable: true
    )]
    #[ValidationRules(rules: 'string|max:50|nullable')]
    public ?string $group_code = null;

    #[OA\Property(
        description: '配置名称',
        type: 'string',
        example: '网站名称',
        nullable: true
    )]
    #[ValidationRules(rules: 'string|max:100|nullable')]
    public ?string $name = null;

    #[OA\Property(
        description: '是否只获取启用的配置项',
        type: 'boolean',
        example: true,
        nullable: true
    )]
    #[ValidationRules(rules: 'boolean|nullable')]
    public ?bool $enabled_only = true;

    #[OA\Property(
        description: '是否包含配置项的完整元数据',
        type: 'boolean',
        example: false,
        nullable: true
    )]
    #[ValidationRules(rules: 'boolean|nullable')]
    public ?bool $with_metadata = false;

    #[OA\Property(
        description: '分组过滤，多个分组用逗号分隔',
        type: 'string',
        example: 'site,oss',
        nullable: true
    )]
    #[ValidationRules(rules: 'string|max:200|nullable')]
    public ?string $group_filter = null;

    #[OA\Property(
        description: '按指定字段作为键名（code 或 id）',
        type: 'string',
        enum: ['code', 'id'],
        example: 'code',
        nullable: true
    )]
    #[ValidationRules(rules: 'in:code,id|nullable')]
    public ?string $key_by = null;

    #[OA\Property(
        description: '是否对分组进行排序',
        type: 'boolean',
        example: false,
        nullable: true
    )]
    #[ValidationRules(rules: 'boolean|nullable')]
    public ?bool $sort_groups = false;

    #[OA\Property(
        description: '是否对配置项进行排序',
        type: 'boolean',
        example: false,
        nullable: true
    )]
    #[ValidationRules(rules: 'boolean|nullable')]
    public ?bool $sort_configs = false;

}