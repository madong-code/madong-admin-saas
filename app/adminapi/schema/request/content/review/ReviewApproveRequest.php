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
    title: '审核通过请求',
    description: '通过审核接口请求参数'
)]
class ReviewApproveRequest extends BaseFormRequest
{
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
        description: '审核意见',
        type: 'string',
        example: '',
        nullable: true
    )]
    #[ValidationRules(rules: 'string|max:255|nullable')]
    public ?string $reason = null;
}
