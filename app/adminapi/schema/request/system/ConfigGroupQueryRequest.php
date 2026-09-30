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


use app\schema\request\BaseQueryRequest;
use OpenApi\Attributes as OA;
use WebmanTech\DTO\Attributes\ValidationRules;

#[OA\Schema(
    title: '分组配置查询',
    description: '获取分组配置接口的请求参数'
)]
class ConfigGroupQueryRequest extends BaseQueryRequest
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
        description: '是否包含配置项的描述信息',
        type: 'boolean',
        example: false,
        nullable: true
    )]
    #[ValidationRules(rules: 'boolean|nullable')]
    public ?bool $with_description = false;

    #[OA\Property(
        description: '是否对配置项进行排序',
        type: 'boolean',
        example: false,
        nullable: true
    )]
    #[ValidationRules(rules: 'boolean|nullable')]
    public ?bool $sort_configs = false;

    #[OA\Property(
        description: '配置项过滤，多个配置编码用逗号分隔',
        type: 'string',
        example: 'site_name,site_url',
        nullable: true
    )]
    #[ValidationRules(rules: 'string|max:200|nullable')]
    public ?string $config_filter = null;

    #[OA\Property(
        description: '是否返回默认值（当配置项不存在时）',
        type: 'boolean',
        example: false,
        nullable: true
    )]
    #[ValidationRules(rules: 'boolean|nullable')]
    public ?bool $with_defaults = false;

    #[OA\Property(
        description: '默认值映射（JSON格式）',
        type: 'string',
        example: '{"site_name":"默认网站名称","site_url":"http://localhost"}',
        nullable: true
    )]
    #[ValidationRules(rules: 'json|nullable')]
    public ?string $default_values = null;
}