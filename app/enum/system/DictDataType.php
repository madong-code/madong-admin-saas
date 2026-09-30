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
 * 字典数据类型枚举
 *
 * @author Mr.April
 * @since  1.0
 */
enum DictDataType:int implements IEnum
{

    case STRING=1;
    case INTEGER=2;

    public function label(): string
    {
         return match($this) {
            self::STRING => '字符串',
            self::INTEGER => '整型',
        };
    }
}
