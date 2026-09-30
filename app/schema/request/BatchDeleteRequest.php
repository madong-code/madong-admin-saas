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
    title: '批量删除请求',
    description: '批量删除请求参数'
)]
class BatchDeleteRequest extends BaseRequestDTO
{
    #[OA\Property(description: '批量删除ID数组', type: 'array', items: new OA\Items(type: 'string'), example: [1, 2])]
    #[ValidationRules(rules: 'array|required|min:1|each:integer|each:min:1|distinct')]
    public array $ids = [];
}
