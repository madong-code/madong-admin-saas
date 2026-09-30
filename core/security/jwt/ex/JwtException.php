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
namespace core\security\jwt\ex;

/**
 * JWT V2 模块基础异常
 */
class JwtException extends \Exception
{
    public function __construct(string $message = '', int $code = -1, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
