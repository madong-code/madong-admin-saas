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

namespace app\adminapi\schema\request\system\org;

use app\schema\request\BaseQueryRequest;
use OpenApi\Attributes as OA;
use WebmanTech\DTO\Attributes\ValidationRules;

#[OA\Schema(
    title: '部门列表查询请求',
    description: '部门列表接口的查询过滤参数'
)]
class DeptQueryRequest extends BaseQueryRequest
{
    #[OA\Property(
        description: '部门编码',
        type: 'string',
        example: 'dev',
        nullable: true
    )]
    #[ValidationRules(rules: 'string|max:50|nullable')]
    public ?string $code = null;

    #[OA\Property(
        description: '部门名称',
        type: 'string',
        example: '开发部',
        nullable: true
    )]
    #[ValidationRules(rules: 'string|max:50|nullable')]
    public ?string $name = null;

    #[OA\Property(
        description: '状态(0:禁用,1:启用)',
        type: 'integer',
        enum: [0, 1],
        example: 1,
        nullable: true
    )]
    #[ValidationRules(rules: 'in:0,1|nullable')]
    public ?int $enabled = null;

    #[OA\Property(
        description: '排序字段',
        type: 'string',
        default: 'sort asc',
        example: 'sort asc'
    )]
    #[ValidationRules(rules: 'string|nullable')]
    public ?string $order = null;

    #[OA\Property(
        description: '返回数据格式(normal|tree|select)',
        type: 'string',
        default: 'normal',
        enum: ['normal', 'tree', 'select'],
        example: 'normal'
    )]
    #[ValidationRules(rules: 'in:normal,tree,select|nullable')]
    public ?string $format = null;
}
