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

namespace core\security\jwt;

use core\security\jwt\ex\JwtException;

/**
 * JWT 签名密钥的生成、校验与 .env 写入
 *
 * 全站唯一入口：JwtToken（启动校验）、InstallService（安装期生成）、
 * jwt:secret 命令（存量升级/轮换）均复用本类，杜绝硬编码回退密钥。
 *
 * @author Mr.April
 * @since  1.0
 */
final class JwtSecret
{
    /** 最小长度（字符） */
    public const MIN_LENGTH = 32;

    /** 历史占位值，出现即视为未配置 */
    private const PLACEHOLDERS = [
        'your-secret-key-change-in-production',
        'default-secret-key-for-development',
    ];

    /**
     * 生成随机密钥（64 位十六进制字符，256 bit 熵）
     */
    public static function generate(): string
    {
        return bin2hex(random_bytes(32));
    }

    /**
     * 判断密钥是否为可用于生产环境的强密钥
     */
    public static function isValid(string $secret): bool
    {
        $secret = trim($secret);

        return $secret !== ''
            && strlen($secret) >= self::MIN_LENGTH
            && !in_array($secret, self::PLACEHOLDERS, true);
    }

    /**
     * fail-fast 校验：缺失、占位或过短密钥直接抛异常，绝不回退到任何公开密钥
     *
     * @throws JwtException
     */
    public static function assertValid(string $secret): void
    {
        if (!self::isValid($secret)) {
            throw new JwtException(
                'JWT_SECRET 未配置或不符合安全要求（至少 32 字符且不得为示例值）。'
                . '请执行 `php webman jwt:secret` 生成随机密钥后重启服务；'
                . '轮换后所有已签发令牌立即失效。'
            );
        }
    }

    /**
     * 确保 .env 内容中含合法 JWT_SECRET
     *
     * 已存在合法密钥时保持幂等（除非 $force）；缺失、空值或占位值时生成随机值。
     *
     * @param string $envContent .env 文件原始内容
     * @param bool   $force      是否强制轮换
     *
     * @return array{content: string, changed: bool} 处理后的内容与是否发生改动
     */
    public static function ensureInEnv(string $envContent, bool $force = false): array
    {
        $current = null;
        if (preg_match('/^JWT_SECRET=(.*)$/m', $envContent, $matches) === 1) {
            $current = trim($matches[1]);
        }

        if (!$force && $current !== null && self::isValid($current)) {
            return ['content' => $envContent, 'changed' => false];
        }

        $secret = self::generate();
        if ($current !== null) {
            $content = preg_replace('/^JWT_SECRET=.*$/m', 'JWT_SECRET=' . $secret, $envContent);
        } else {
            $content = rtrim($envContent, "\r\n") . "\nJWT_SECRET=" . $secret . "\n";
        }

        return ['content' => (string)$content, 'changed' => true];
    }
}
