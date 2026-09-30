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
 * 会员积分来源枚举
 */
enum PointSource: string implements IEnum
{
    case SIGN_IN = 'sign_in';         // 签到
    case SHOPPING = 'shopping';       // 购物
    case EXCHANGE = 'exchange';       // 兑换
    case INVITE = 'invite';           // 邀请
    case REGISTER = 'register';       // 注册
    case ACTIVITY = 'activity';       // 活动
    case EXPIRED = 'expired';         // 过期
    case PENALTY = 'penalty';         // 处罚
    case ADMIN = 'admin';             // 后台调整
    case OTHER = 'other';             // 其他
    
    /**
     * 获取文本描述
     */
    public function text(): string
    {
        return match($this) {
            self::SIGN_IN => '签到',
            self::SHOPPING => '购物',
            self::EXCHANGE => '兑换',
            self::INVITE => '邀请',
            self::REGISTER => '注册',
            self::ACTIVITY => '活动',
            self::EXPIRED => '过期',
            self::PENALTY => '处罚',
            self::ADMIN => '后台调整',
            self::OTHER => '其他',
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