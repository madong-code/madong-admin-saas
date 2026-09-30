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

/**
 *
 * 操作结果枚举
 * @author Mr.April
 * @since  1.0
 */
enum OperationResult:int
{
    case SUCCESS = 0;
    case FAILURE = -1;

    public function label(): string
    {
        return match($this) {
            self::SUCCESS => '成功',
            self::FAILURE => '失败'
        };
    }


    public function color():string{
         return match($this) {
            self::SUCCESS => 'success',
            self::FAILURE => 'error'
        };
    }
}
