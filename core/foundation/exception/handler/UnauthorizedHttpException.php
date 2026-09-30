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
namespace core\foundation\exception\handler;

use core\foundation\exception\handler\BaseException;

class UnauthorizedHttpException extends BaseException
{
    /**
     * @var int
     */
    public int $statusCode = 401;

    /**
     * @var string
     */
    public string $errorMessage = '未经授权的访问，请先登录';
}
