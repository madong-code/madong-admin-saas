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

enum UserAdminType:int implements IEnum
{
     // 枚举成员定义
    case SUPER_ADMIN = 1;
    case NORMAL_ADMIN = 2;

    public const CODE = 'sys_user_admin_type';

    /**
     * 获取显示标签
     */
    public function label(): string
    {
        return match($this) {
            self::SUPER_ADMIN => '超级管理员',
            self::NORMAL_ADMIN => '普通管理员',
        };
    }

}
