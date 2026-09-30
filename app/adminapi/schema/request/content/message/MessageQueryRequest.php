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
namespace app\adminapi\schema\request\content\message;


use app\schema\request\BaseQueryRequest;
use OpenApi\Attributes as OA;
use WebmanTech\DTO\Attributes\ValidationRules;

#[OA\Schema(
    title: '消息列表查询请求',
    description: '消息列表接口的查询过滤参数'
)]
class MessageQueryRequest extends BaseQueryRequest
{
    #[OA\Property(
        property: 'title',
        description: '消息标题',
        type: 'string',
        example: '系统通知',
        nullable: true
    )]
    #[ValidationRules(rules: 'string|max:100|nullable')]
    public ?string $title = null;

    #[OA\Property(
        property: 'content',
        description: '消息内容',
        type: 'string',
        example: '系统升级',
        nullable: true
    )]
    #[ValidationRules(rules: 'string|max:255|nullable')]
    public ?string $content = null;

    #[OA\Property(
        property: 'status',
        description: '消息状态（0=未读，1=已读）',
        type: 'integer',
        enum: [0, 1],
        example: 0,
        nullable: true
    )]
    #[ValidationRules(rules: 'in:0,1|nullable')]
    public ?int $status = null;

    #[OA\Property(
        property: 'priority',
        description: '优先级（1=低，2=中，3=高）',
        type: 'integer',
        enum: [1, 2, 3],
        example: 3,
        nullable: true
    )]
    #[ValidationRules(rules: 'in:1,2,3|nullable')]
    public ?int $priority = null;
}
