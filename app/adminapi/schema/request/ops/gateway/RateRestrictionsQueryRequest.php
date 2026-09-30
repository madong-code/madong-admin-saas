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
    title: '限访名单列表查询请求',
    description: '限访名单列表接口查询过滤参数'
)]
class RateRestrictionsQueryRequest extends BaseQueryRequest
{
    #[OA\Property(
        property: 'id',
        description: '名单ID',
        type: 'string',
        example: "247306532294254592",
        nullable: true
    )]
    public ?string $id = null;

    #[OA\Property(
        property: 'name',
        description: '名单名称（模糊匹配）',
        type: 'string',
        example: "sss",
        nullable: true
    )]
    public ?string $name = null;

    #[OA\Property(
        property: 'ip',
        description: 'IP地址（模糊匹配）',
        type: 'string',
        example: null,
        nullable: true
    )]
    public ?string $ip = null;

    #[OA\Property(
        property: 'enabled',
        description: '是否启用(0:禁用,1:启用)',
        type: 'integer',
        enum: [0, 1],
        example: 1,
        nullable: true
    )]
    public ?int $enabled = null;

    #[OA\Property(
        property: 'methods',
        description: '请求方法',
        type: 'string',
        example: "GET",
        nullable: true
    )]
    public ?string $methods = null;

    #[OA\Property(
        property: 'path',
        description: '请求路径（模糊匹配）',
        type: 'string',
        example: "/system/menu",
        nullable: true
    )]
    public ?string $path = null;

    #[OA\Property(
        property: 'priority',
        description: '优先级',
        type: 'integer',
        example: 100,
        nullable: true
    )]
    public ?int $priority = null;
}
