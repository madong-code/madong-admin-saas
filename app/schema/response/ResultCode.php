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

#[OA\Schema(
    title: 'API响应状态',
    type: 'integer',
    default: 0
)]
enum ResultCode: int
{

    case SUCCESS = 0;

    case FAIL = -1;

    case UNAUTHORIZED = 401;

    case FORBIDDEN = 403;

    case NOT_FOUND = 404;

    case METHOD_NOT_ALLOWED = 405;

    case NOT_ACCEPTABLE = 406;

    case UNPROCESSABLE_ENTITY = 422;

    case DISABLED = 423;
}
