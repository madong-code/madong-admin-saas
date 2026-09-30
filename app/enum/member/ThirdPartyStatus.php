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
namespace app\enum\member;

use core\foundation\interface\IEnum;

/**
 * 会员第三方状态枚举
 */
enum ThirdPartyStatus: int implements IEnum
{
    case DISABLED = 0;      // 禁用
    case ENABLED = 1;       // 启用
    
    /**
     * 获取文本描述
     */
    public function text(): string
    {
        return match($this) {
            self::DISABLED => '禁用',
            self::ENABLED => '启用',
        };
    }

    /**
     * 实现IEnum接口的label方法
     */
    public function label(): string
    {
        return $this->text();
    }
    
    /**
     * 获取所有枚举值
     */
    public static function all(): array
    {
        return array_column(self::cases(), 'value');
    }
    
    /**
     * 获取所有枚举映射
     */
    public static function map(): array
    {
        $map = [];
        foreach (self::cases() as $case) {
            $map[$case->value] = $case->text();
        }
        return $map;
    }
}
