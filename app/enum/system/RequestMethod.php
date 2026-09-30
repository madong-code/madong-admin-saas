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

namespace app\enum\system;

use core\foundation\interface\IEnum;

/**
 * HTTP请求方式枚举
 *
 * @author Mr.April
 * @since  1.0
 */
enum RequestMethod: string implements IEnum
{

    case GET = 'GET';     // HTTP GET方法
    case POST = 'POST';   // HTTP POST方法
    case PUT = 'PUT';     // HTTP PUT方法
    case DELETE = 'DELETE'; // HTTP DELETE方法

    public function label(): string
    {
        return match ($this) {
            self::GET => 'GET',
            self::POST => 'POST',
            self::PUT => 'PUT',
            self::DELETE => 'DELETE',
        };
    }
}
