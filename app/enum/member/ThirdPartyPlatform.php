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
 * 会员第三方平台枚举
 */
enum ThirdPartyPlatform: int implements IEnum
{
    case QQ = 1;           // QQ
    case WECHAT = 2;       // 微信
    case WEIBO = 3;        // 微博
    case DOUYIN = 4;       // 抖音
    
    /**
     * 获取文本描述
     */
    public function text(): string
    {
        return match($this) {
            self::QQ => 'QQ',
            self::WECHAT => '微信',
            self::WEIBO => '微博',
            self::DOUYIN => '抖音',
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
