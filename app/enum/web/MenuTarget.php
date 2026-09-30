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
namespace app\enum\web;

use core\foundation\interface\IEnum;

/**
 * 目标窗口枚举
 */
enum MenuTarget: int implements IEnum
{
    case SELF = 1;  // 当前窗口
    case BLANK = 2; // 新窗口

    /**
     * 获取文本
     */
    public function text(): string
    {
        return match ($this) {
            self::SELF => '当前窗口',
            self::BLANK => '新窗口',
        };
    }

    /**
     * 获取标签
     */
    public function label(): string
    {
        return $this->text();
    }
}
