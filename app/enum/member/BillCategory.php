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
 * 账单分类枚举
 */
enum BillCategory: int implements IEnum
{
    case RECHARGE = 1;      // 充值
    case WITHDRAW = 2;      // 提现
    case ORDER = 3;         // 订单
    case REFUND = 4;        // 退款
    case POINTS = 5;        // 积分
    case SIGN = 6;          // 签到
    case OTHER = 99;        // 其他
    
    /**
     * 获取文本描述
     */
    public function text(): string
    {
        return match($this) {
            self::RECHARGE => '充值',
            self::WITHDRAW => '提现',
            self::ORDER => '订单',
            self::REFUND => '退款',
            self::POINTS => '积分',
            self::SIGN => '签到',
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