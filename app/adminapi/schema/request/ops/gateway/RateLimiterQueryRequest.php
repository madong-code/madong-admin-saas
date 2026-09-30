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
namespace app\adminapi\schema\request\ops\gateway;

use app\schema\request\BaseQueryRequest;
use OpenApi\Attributes as OA;

#[OA\Schema(
    title: '限访规则列表查询请求',
    description: '限访规则接口查询过滤参数'
)]
class RateLimiterQueryRequest extends BaseQueryRequest
{

    #[OA\Property(
        property: 'id',
        description: '规则ID',
        type: 'string',
        example: '247298887944531968',
        nullable: true
    )]
    public ?string $id = null;

    #[OA\Property(
        property: 'name',
        description: '规则名称（模糊匹配）',
        type: 'string',
        example: 'customized',
        nullable: true
    )]
    public ?string $name = null;

    #[OA\Property(
        property: 'match_type',
        description: '匹配类型',
        type: 'string',
        example: 'allow',
        nullable: true
    )]
    public ?string $match_type = null;

    #[OA\Property(
        property: 'methods',
        description: '请求方法',
        type: 'string',
        example: 'GET',
        nullable: true
    )]
    public ?string $methods = null;

    #[OA\Property(
        property: 'path',
        description: '请求路径（模糊匹配）',
        type: 'string',
        example: '/system/menu',
        nullable: true
    )]
    public ?string $path = null;

    #[OA\Property(
        property: 'enabled',
        description: '是否启用（0:禁用,1:启用）',
        type: 'integer',
        enum: [0, 1],
        example: 1,
        nullable: true
    )]
    public ?int $enabled = null;

    #[OA\Property(
        property: 'limit_value',
        description: '限制次数',
        type: 'integer',
        example: 60,
        nullable: true
    )]
    public ?int $limit_value = null;

    #[OA\Property(
        property: 'period',
        description: '限制周期（秒）',
        type: 'integer',
        example: 1,
        nullable: true
    )]
    public ?int $period = null;
}
