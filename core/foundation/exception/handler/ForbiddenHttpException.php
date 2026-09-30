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

class ForbiddenHttpException extends BaseException
{
    /**
     * @var int
     */
    public int $statusCode = 403;

    /**
     * @var string
     */
    public string $errorMessage = '对不起，您没有该接口访问权限，请联系管理员';
}
