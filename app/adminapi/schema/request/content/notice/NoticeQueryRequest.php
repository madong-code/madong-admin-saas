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
namespace app\adminapi\schema\request\content\notice;


use app\schema\request\BaseQueryRequest;
use OpenApi\Attributes as OA;
use WebmanTech\DTO\Attributes\ValidationRules;

#[OA\Schema(
    title: '公告列表查询请求',
    description: '公告列表接口的查询过滤参数'
)]
class NoticeQueryRequest extends BaseQueryRequest
{
    #[OA\Property(
        property: 'title',
        description: '公告标题',
        type: 'string',
        example: '系统公告',
        nullable: true
    )]
    #[ValidationRules(rules: 'string|max:100|nullable')]
    public ?string $title = null;

    #[OA\Property(
        property: 'type',
        description: '公告类型',
        type: 'string',
        enum: ['notice', 'announcement', 'alert'],
        example: 'notice',
        nullable: true
    )]
    #[ValidationRules(rules: 'string|in:notice,announcement,alert|nullable')]
    public ?string $type = null;

    #[OA\Property(
        property: 'enabled',
        description: '是否启用（1启用 0禁用）',
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
        example: '2025-10-01',
        nullable: true
    )]
    #[ValidationRules(rules: 'date|nullable')]
    public ?string $start_date = null;

    #[OA\Property(
        property: 'end_date',
        description: '创建结束日期',
        type: 'string',
        format: 'date',
        example: '2025-10-31',
        nullable: true
    )]
    #[ValidationRules(rules: 'date|after_or_equal:start_date|nullable')]
    public ?string $end_date = null;

}
