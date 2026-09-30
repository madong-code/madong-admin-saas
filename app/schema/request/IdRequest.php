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

use OpenApi\Attributes as OA;
use madong\swagger\schema\BaseRequestDTO;
use WebmanTech\DTO\Attributes\ValidationRules;

#[OA\Schema(
    title: 'ID查询请求',
    description: '根据ID查询单条记录的请求参数'
)]
class IdRequest extends BaseRequestDTO
{
    #[OA\Property(
        property: 'id',
        description: '记录ID',
        type: 'string',
        example: 1
    )]
    #[ValidationRules(rules: 'string|required|min:1')]
    public int $id;

}
