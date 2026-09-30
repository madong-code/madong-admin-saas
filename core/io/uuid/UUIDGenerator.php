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
namespace core\io\uuid;

/**
 * uuid
 *
 * @author Mr.April
 * @since  1.0
 */
class UUIDGenerator
{
    /**
     * 生成UUID
     *
     * @param string $format
     * @param int    $length
     *
     * @return string|null
     * @throws \Exception
     */
    public static function generate(string $format = 'uuid', int $length = 36): ?string
    {
        if ($format === 'uuid') {
            return self::generateUUID();
        } elseif ($format === 'custom') {
            return self::generateCustomUUID($length);
        }
        return null;
    }

    /**
     * 生成标准格式UUID
     *
     * @return string
     * @throws \Exception
     */
    private static function generateUUID(): string
    {
        // 生成标准UUID
        return sprintf(
            '%s-%s-%s-%s-%s',
            bin2hex(random_bytes(4)),
            bin2hex(random_bytes(2)),
            bin2hex(random_bytes(2)),
            bin2hex(random_bytes(2)),
            bin2hex(random_bytes(6))
        );
    }

    /**
     * 自定义UUID
     *
     * @param $length
     *
     * @return string|null
     * @throws \Exception
     */
    private static function generateCustomUUID($length): ?string
    {
        // 生成自定义长度的UUID
        if ($length < 1) {
            return null; // 长度必须大于0
        }
        return strtoupper(substr(bin2hex(random_bytes($length / 2)), 0, $length));
    }
}
