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

namespace app\schema\request\plugin;

use madong\swagger\schema\BaseRequestDTO;
use OpenApi\Attributes as OA;
use WebmanTech\DTO\Attributes\ValidationRules;

#[OA\Schema(
    title: '租户插件状态切换请求',
    description: '切换租户插件的启用/停用状态'
)]
class TenantPluginToggleStatusRequest extends BaseRequestDTO
{
    #[OA\Property(
        property: 'status',
        description: '目标状态（0:停用,1:启用）',
        type: 'integer',
        enum: [0, 1],
        example: 1
    )]
    #[ValidationRules(rules: 'required|in:0,1')]
    public int $status;
}
