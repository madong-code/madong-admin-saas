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
namespace core\foundation\tool;

use core\foundation\exception\handler\AdminException;

/**
 * 断言辅助类
 */
final class AssertHelper
{

    /**
     * 断言表达式为 true
     *
     * @param mixed  $expression 要检查的值（会自动转换为bool）
     * @param string $message    自定义错误消息
     *
     * @throws \core\foundation\exception\handler\AdminException() 如果表达式不为true
     */
    public static function isTrue(mixed $expression, string $message = "[Assertion failed] - Expected value to be true"): void
    {
        if (!filter_var($expression, FILTER_VALIDATE_BOOL)) {
            throw new AdminException($message);
        }
    }

    /**
     * 断言表达式为 false
     *
     * @throws \core\foundation\exception\handler\AdminException
     */
    public static function isFalse(mixed $expression, string $message = "[Assertion failed] - Expected value to be false"): void
    {
        if (filter_var($expression, FILTER_VALIDATE_BOOL)) {
            throw new AdminException($message);
        }
    }

    /**
     * 断言值为 null
     *
     * @throws \core\foundation\exception\handler\AdminException
     */
    public static function isNull(mixed $value, string $message = "[Assertion failed] - Expected value to be null"): void
    {
        if ($value !== null) {
            throw new AdminException($message);
        }
    }

    /**
     * 断言值不为 null
     *
     * @throws \core\foundation\exception\handler\AdminException
     */
    public static function notNull(mixed $value, string $message = "[Assertion failed] - Value cannot be null"): void
    {
        if ($value === null) {
            throw new AdminException($message);
        }
    }

    /**
     * 断言字符串非空（不为null且不为空字符串）
     *
     * @throws \core\foundation\exception\handler\AdminException
     */
    public static function notEmpty(?string $value, string $message = "[Assertion failed] - Value cannot be empty"): void
    {
        if ($value === null || trim($value) === '') {
            throw new AdminException($message);
        }
    }

    /**
     * 断言数组非空
     *
     * @throws \core\foundation\exception\handler\AdminException
     */
    public static function notEmptyArray(array $array, string $message = "[Assertion failed] - Array cannot be empty"): void
    {
        if (empty($array)) {
            throw new AdminException($message);
        }
    }

    /**
     * 断言值为指定类型
     *
     * @throws \core\foundation\exception\handler\AdminException
     */
    public static function isInstanceOf(mixed $value, string $className, ?string $message = null): void
    {
        if (!($value instanceof $className)) {
            $message = $message ?? sprintf("[Assertion failed] - Expected instance of %s", $className);
            throw new AdminException($message);
        }
    }

    /**
     * 断言值为数字或数字字符串
     *
     * @throws \core\foundation\exception\handler\AdminException
     */
    public static function isNumeric(mixed $value, string $message = "[Assertion failed] - Expected a numeric value"): void
    {
        if (!is_numeric($value)) {
            throw new AdminException($message);
        }
    }

    /**
     * 断言值在指定范围内
     *
     * @throws \core\foundation\exception\handler\AdminException
     */
    public static function inRange(mixed $value, mixed $min, mixed $max, ?string $message = null): void
    {
        if ($value < $min || $value > $max) {
            $message = $message ?? sprintf("[Assertion failed] - Value must be between %s and %s", $min, $max);
            throw new AdminException($message);
        }
    }

    /**
     * 断言字符串匹配正则表达式
     *
     * @throws \core\foundation\exception\handler\AdminException
     */
    public static function matchesRegex(string $value, string $pattern, ?string $message = null): void
    {
        if (!preg_match($pattern, $value)) {
            $message = $message ?? sprintf("[Assertion failed] - Value does not match pattern %s", $pattern);
            throw new AdminException($message);
        }
    }

    /**
     * 断言两个值相等（松散比较 ==）
     *
     * @throws \core\foundation\exception\handler\AdminException
     */
    public static function equals(mixed $actual, mixed $expected, ?string $message = null): void
    {
        if ($actual != $expected) {
            $message = $message ?? sprintf("[Assertion failed] - Expected %s, got %s", var_export($expected, true), var_export($actual, true));
            throw new AdminException($message);
        }
    }

    /**
     * 断言两个值严格相等（===）
     *
     * @throws \core\foundation\exception\handler\AdminException
     */
    public static function strictEquals(mixed $actual, mixed $expected, ?string $message = null): void
    {
        if ($actual !== $expected) {
            $message = $message ?? sprintf("[Assertion failed] - Expected strict %s, got %s", var_export($expected, true), var_export($actual, true));
            throw new AdminException($message);
        }
    }

    /**
     * 断言值为有效的电子邮件格式
     *
     * @throws \core\foundation\exception\handler\AdminException
     */
    public static function isEmail(string $value, string $message = "[Assertion failed] - Value is not a valid email address"): void
    {
        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
            throw new AdminException($message);
        }
    }

    /**
     * 断言值为有效的URL格式
     *
     * @throws \core\foundation\exception\handler\AdminException
     */
    public static function isUrl(string $value, string $message = "[Assertion failed] - Value is not a valid URL"): void
    {
        if (!filter_var($value, FILTER_VALIDATE_URL)) {
            throw new AdminException($message);
        }
    }

    /**
     * 断言目录存在
     *
     * @throws \core\foundation\exception\handler\AdminException
     */
    public static function directoryExists(string $path, ?string $message = null): void
    {
        if (!is_dir($path)) {
            $message = $message ?? sprintf("[Assertion failed] - Directory %s does not exist", $path);
            throw new AdminException($message);
        }
    }

    /**
     * 断言文件存在
     *
     * @throws \core\foundation\exception\handler\AdminException
     */
    public static function fileExists(string $path, ?string $message = null): void
    {
        if (!file_exists($path)) {
            $message = $message ?? sprintf("[Assertion failed] - File %s does not exist", $path);
            throw new AdminException($message);
        }
    }
}
