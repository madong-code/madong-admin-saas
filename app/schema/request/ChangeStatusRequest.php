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

namespace app\schema\request;

use madong\swagger\schema\BaseRequestDTO;
use OpenApi\Attributes as OA;
use WebmanTech\DTO\Attributes\ValidationRules;

#[OA\Schema(
    title: '状态变更请求',
    description: '状态变更请求参数'
)]
class ChangeStatusRequest extends BaseRequestDTO
{

    #[OA\Parameter(
        name: 'id',
        description: '任务ID',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'string', example: 1)
    )]
    #[ValidationRules(rules: 'string|required|in:0,1')]
    public int $id;

    #[OA\Property(
        property: 'enabled',
        description: '目标状态（0:禁用,1:启用）',
        type: 'integer',
        enum: [0, 1],
        example: 1
    )]
    #[ValidationRules(rules: 'string|required|in:0,1')]
    public int $enabled;

}
