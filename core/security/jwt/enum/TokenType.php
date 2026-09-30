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
namespace core\security\jwt\enum;

/**
 * Token 类型枚举
 */
enum TokenType: string
{
    case ACCESS = 'access';
    case REFRESH = 'refresh';

    /**
     * 获取枚举值
     */
    public function value(): string
    {
        return $this->value;
    }
}
