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

namespace app\enum\review;

use core\foundation\interface\IEnum;

/**
 * 审核状态枚举（原生 backed enum，可经 ->value 取整型值）
 *
 * 状态流转：
 * - PENDING    待审核（simple 模式等待管理员处理）
 * - PROCESSING 审批中（已提交外部审批流，等待回调）
 * - APPROVED   已通过
 * - REJECTED   已拒绝
 * - CANCELED   已取消
 */
enum ReviewStatus: int implements IEnum
{
    case PENDING = 0;     // 待审核
    case APPROVED = 1;    // 已通过
    case REJECTED = 2;    // 已拒绝
    case CANCELED = 3;    // 已取消
    case PROCESSING = 4;  // 审批流处理中

    public function label(): string
    {
        return match ($this) {
            self::PENDING    => '待审核',
            self::PROCESSING => '审批中',
            self::APPROVED   => '已通过',
            self::REJECTED   => '已拒绝',
            self::CANCELED   => '已取消',
        };
    }

    /**
     * 获取标签语义色（供前端 CellDictTag 着色）
     */
    public function color(): string
    {
        return match ($this) {
            self::PENDING    => 'orange',   // 待审核
            self::PROCESSING => 'blue',     // 审批中
            self::APPROVED   => 'green',    // 已通过
            self::REJECTED   => 'red',      // 已拒绝
            self::CANCELED   => 'gray',     // 已取消
        };
    }

    public static function getOptions(): array
    {
        return [
            ['value' => self::PENDING->value, 'label' => self::PENDING->label()],
            ['value' => self::PROCESSING->value, 'label' => self::PROCESSING->label()],
            ['value' => self::APPROVED->value, 'label' => self::APPROVED->label()],
            ['value' => self::REJECTED->value, 'label' => self::REJECTED->label()],
            ['value' => self::CANCELED->value, 'label' => self::CANCELED->label()],
        ];
    }

    public static function getLabel($value): string
    {
        try {
            return self::from((int) $value)->label();
        } catch (\Throwable $e) {
            return '未知';
        }
    }

    public static function isValid($value): bool
    {
        return in_array((int) $value, array_column(self::getOptions(), 'value'), true);
    }

    public static function fromValue($value): self
    {
        return self::from((int) $value);
    }
}
