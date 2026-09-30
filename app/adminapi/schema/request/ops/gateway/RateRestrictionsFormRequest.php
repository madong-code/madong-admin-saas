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

use app\schema\request\BaseFormRequest;
use OpenApi\Attributes as OA;

#[OA\Schema(
    title: '限访名单表单',
    description: '限访名单创建和编辑接口共用的表单请求参数'
)]
class RateRestrictionsFormRequest extends BaseFormRequest
{

    #[OA\Property(
        property: 'name',
        description: '名单名称',
        type: 'string',
        example: ""
    )]
    public string $name;

    #[OA\Property(
        property: 'ip',
        description: 'IP地址（留空表示不限IP）',
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
        example: 1
    )]
    public int $enabled;

    #[OA\Property(
        property: 'priority',
        description: '优先级（数值越大优先级越高）',
        type: 'integer',
        example: 100
    )]
    public int $priority;

    #[OA\Property(
        property: 'methods',
        description: '请求方法（多个用逗号分隔，如：GET,POST）',
        type: 'string',
        example: "GET"
    )]
    public string $methods;

    #[OA\Property(
        property: 'path',
        description: '请求路径',
        type: 'string',
        example: "/system/menu"
    )]
    public string $path;

    #[OA\Property(
        property: 'message',
        description: '限制提示消息',
        type: 'string',
        example: "限制访问"
    )]
    public string $message;

    #[OA\Property(
        property: 'start_time',
        description: '生效开始时间（UTC格式，如：2025-11-13T00:00:00Z）',
        type: 'string',
        format: 'date-time',
        example: null,
        nullable: true
    )]
    public ?string $start_time = null;

    #[OA\Property(
        property: 'end_time',
        description: '生效结束时间（UTC格式，如：2025-11-14T00:00:00Z）',
        type: 'string',
        format: 'date-time',
        example: null,
        nullable: true
    )]
    public ?string $end_time = null;

    #[OA\Property(
        property: 'remark',
        description: '备注信息',
        type: 'string',
        example: null,
        nullable: true
    )]
    public ?string $remark = null;
}
