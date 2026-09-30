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
namespace core\infrastructure\logger\enum;

use core\foundation\interface\IEnum;
use Error;
use ValueError;

/**
 * 日志级别
 *
 * @author Mr.April
 * @since  1.0
 */
enum LogLevel: int implements IEnum
{
    case DEBUG = 100;
    case INFO = 200;
    case NOTICE = 250;
    case WARNING = 300;
    case ERROR = 400;
    case CRITICAL = 500;
    case ALERT = 550;
    case EMERGENCY = 600;

    /* 基础方法 */

    public function label(): string
    {
        return strtolower($this->name);
    }

    public function value(): int
    {
        return $this->value;
    }

    /* 工厂方法 */

    public static function fromName(string $name): ?self
    {
        try {
            return constant("self::" . strtoupper($name));
        } catch (Error) {
            return null;
        }
    }

    public static function tryFromName(string $name): self
    {
        return self::fromName($name) ?? throw new ValueError("Invalid log level: $name");
    }

    /* 实用方法 */

    public static function valuesArray(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function namesArray(): array
    {
        return array_column(self::cases(), 'name');
    }

    public static function isValid(string|int $level): bool
    {
        if (is_int($level)) {
            return in_array($level, self::valuesArray(), true);
        }

        return self::fromName($level) !== null;
    }

    public static function getMinLevel(): self
    {
        return self::DEBUG;
    }

    public static function getMaxLevel(): self
    {
        return self::EMERGENCY;
    }

    /* 比较方法 */

    public function isHigherThan(self $other): bool
    {
        return $this->value > $other->value;
    }

    public function isLowerThan(self $other): bool
    {
        return $this->value < $other->value;
    }

    /* 转换方法 */

    public function toPsr3Level(): string
    {
        return $this->label();
    }

    public function toMonologLevel(): int
    {
        return $this->value;
    }

}
