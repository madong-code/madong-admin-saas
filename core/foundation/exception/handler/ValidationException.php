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

/**
 * 验证异常类
 *
 * @author Mr.April
 * @since  1.0
 */
class ValidationException extends BaseException
{

    /**
     * @var int
     */
    public int $statusCode = 400;


    public int $errorCode=422;



}
