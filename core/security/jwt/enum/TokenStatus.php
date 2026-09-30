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
 * Token 状态枚举
 */
enum TokenStatus: string
{
    case ACTIVE = 'active';
    case REFRESHED = 'refreshed';
    case REVOKED = 'revoked';
    case EXPIRED = 'expired';

    /**
     * 获取枚举值
     */
    public function value(): string
    {
        return $this->value;
    }
}
