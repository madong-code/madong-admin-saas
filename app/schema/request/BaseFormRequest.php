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

#[OA\Schema(
    title: '基础表单请求',
    description: '通用表单请求基类'
)]
abstract class BaseFormRequest extends BaseRequestDTO
{

      #[OA\Property(
        property: 'id',
        description: 'ID',
        type: 'string',
        example: '246996721795072000',
        nullable: false
    )]
    public string|int|null $id ;

}
