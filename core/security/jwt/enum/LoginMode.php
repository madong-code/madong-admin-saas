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
 * 登录模式枚举
 */
enum LoginMode: string
{
    /**
     * 单端登录模式 - 同一用户只能在一个设备登录，新登录会踢掉旧登录
     */
    case SINGLE = 'single';

    /**
     * 客户端模式 - 同一客户端只能在一个设备登录，不同客户端可同时登录
     */
    case CLIENT = 'client';

    /**
     * 多端模式 - 同一用户可在多个设备同时登录
     */
    case MULTI = 'multi';

    /**
     * 获取枚举值
     */
    public function value(): string
    {
        return $this->value;
    }
}
