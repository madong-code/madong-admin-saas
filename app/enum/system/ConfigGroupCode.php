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
 * 配置分组枚举
 *
 * @author Mr.April
 * @since  1.0
 */
enum ConfigGroupCode: string implements IEnum
{
    case DEFAULT = 'default';
    case PUBLIC = 'public';
    case CUSTOM = 'custom';

    public function label(): string
    {
        return match ($this) {
            self::DEFAULT => '默认配置',
            self::PUBLIC => '公共配置',
            self::CUSTOM => '自定义配置',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::DEFAULT => '',
            self::PUBLIC => 'success',
            self::CUSTOM => 'warning',
        };
    }
}
