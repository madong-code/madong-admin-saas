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

namespace app\adminapi\schema\request\content\review;

use app\schema\request\BaseFormRequest;
use OpenApi\Attributes as OA;
use WebmanTech\DTO\Attributes\ValidationRules;

#[OA\Schema(
    title: '审核批量请求',
    description: '批量通过/拒绝审核接口请求参数'
)]
class ReviewBatchRequest extends BaseFormRequest
{
    #[OA\Property(
        property: 'ids',
        description: '审核记录ID数组',
        type: 'array',
        items: new OA\Items(type: 'string'),
        example: [1, 2]
    )]
    #[ValidationRules(rules: 'require|array')]
    public array $ids;

    #[OA\Property(
        property: 'force',
        description: '是否强制（超审批，仅超级管理员可破外部审批流锁定）',
        type: 'boolean',
        example: false
    )]
    #[ValidationRules(rules: 'boolean')]
    public ?bool $force = false;

    #[OA\Property(
        property: 'reason',
        description: '审核意见（批量拒绝时必填）',
        type: 'string',
        example: '',
        nullable: true
    )]
    #[ValidationRules(rules: 'string|max:255|nullable')]
    public ?string $reason = null;
}
