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
 * Official Website: https://madong.tech
 */

namespace app\enum\system;

use core\foundation\interface\IEnum;

/**
 * Casbin 策略前缀
 *
 * @author Mr.April
 * @since  1.0
 */
enum PolicyPrefix: string implements IEnum
{

    // 核心权限类型
    case ROLE = 'role:';                 // 角色权限 例: role:admin
    case SUBSCRIPTION = 'sub:';          // 租户订阅套餐权限 例: sub:enterprise
    case TENANT_PACKAGE = 'tenant_pkg:'; // 租户专属套餐 例: tenant_pkg:basic
    case TIER = 'tier:';                 // 套餐等级权限 例: tier:gold
    case DEV = 'dev:';                   // 开发相关权限 例: dev:crontab

    // 特殊权限类型
    case DOMAIN = 'domain:';             // 租户域标识 例: domain:1
    case USER = 'user:';                 // 用户直接权限 例: user:1001

    case MENU = "menu:";
    case ROUTE = "route:";
    case BUTTON = "button:";

    /**
     * 获取人类可读的标签
     */
    public function label(): string
    {
        return match ($this) {
            self::ROLE => '角色权限',
            self::SUBSCRIPTION => '租户订阅套餐权限',
            self::TENANT_PACKAGE => '租户专属套餐',
            self::TIER => '套餐等级权限',
            self::DEV => '开发相关权限',
            self::DOMAIN => '租户域标识',
            self::USER => '用户直接权限',
            self::MENU => '菜单资源标识',
            self::ROUTE => '路由资源标识',
            self::BUTTON => '按钮资源标识'
        };
    }
}
