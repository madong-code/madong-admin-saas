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
namespace app\adminapi\schema\request\system\org;



use app\schema\request\BaseQueryRequest;
use OpenApi\Attributes as OA;
use WebmanTech\DTO\Attributes\ValidationRules;

#[OA\Schema(
    title: '岗位列表查询请求',
    description: '岗位列表接口的查询过滤参数'
)]
class PostQueryRequest extends BaseQueryRequest
{


    #[OA\Property(
        property: 'name',
        description: '岗位名称',
        type: 'string',
        example: '技术主管',
        nullable: true
    )]
    #[ValidationRules(rules: 'string|max:50|nullable')]
    public ?string $name = null;

    #[OA\Property(
        property: 'code',
        description: '岗位编码',
        type: 'string',
        example: 'TECH_LEAD',
        nullable: true
    )]
    #[ValidationRules(rules: 'string|max:30|nullable')]
    public ?string $code = null;

    #[OA\Property(
        property: 'dept_id',
        description: '部门ID',
        type: 'string',
        example: '246996721795072000',
        nullable: true
    )]
    #[ValidationRules(rules: 'string|nullable')]
    public ?string $dept_id = null;

    #[OA\Property(
        property: 'enabled',
        description: '状态（1启用 0禁用）',
        type: 'integer',
        enum: [0, 1],
        example: 1,
        nullable: true
    )]
    #[ValidationRules(rules: 'integer|in:0,1|nullable')]
    public ?int $enabled = null;

    #[OA\Property(
        property: 'start_date',
        description: '创建开始日期',
        type: 'string',
        format: 'date',
        example: '2025-11-01',
        nullable: true
    )]
    #[ValidationRules(rules: 'date|nullable')]
    public ?string $start_date = null;

    #[OA\Property(
        property: 'end_date',
        description: '创建结束日期',
        type: 'string',
        format: 'date',
        example: '2025-11-30',
        nullable: true
    )]
    #[ValidationRules(rules: 'date|after_or_equal:start_date|nullable')]
    public ?string $end_date = null;
}
