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
 * 租户成员类型
 *
 * @author Mr.April
 * @since  1.0
 */
enum TenantAdminType: int implements IEnum
{
    // 枚举成员定义
    case ADMIN = 1;
    case NORMAL_ADMIN = 2;

    /**
     * 获取显示标签
     */
    public function label(): string
    {
        return match ($this) {
            self::ADMIN => '管理员',
            self::NORMAL_ADMIN => '普通成员',
        };
    }

    // 设置标签颜色
    public function color(): string
    {
        return match ($this) {
            self::ADMIN => '#2196F3',
            self::NORMAL_ADMIN => '#4CAF50',
        };
    }

}
