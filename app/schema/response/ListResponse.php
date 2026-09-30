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

namespace app\schema\response;

use OpenApi\Attributes as OA;
use madong\swagger\schema\BaseResponseDTO;

#[OA\Schema(
    title: '列表数据响应',
    description: '列表数据响应结构'
)]
class ListResponse extends BaseResponseDTO
{
    #[OA\Property(
        property: 'list',
        description: '数据列表',
        type: 'array',
        items: new OA\Items(),
        example: []
    )]
    public array $list;

}
