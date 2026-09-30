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

use phpseclib3\Crypt\PublicKeyLoader;
use phpseclib3\Crypt\RSA;

/**
 * RSA秘钥服务
 *
 * @author Mr.April
 * @since  1.0
 */
class RSAService
{

    /**
     * 生成秘钥对
     *
     * @param int $bits
     *
     * @return array
     */
    public static function generateKeys(int $bits = 2048): array
    {
        $privateKey = RSA::createKey($bits);

        $privatePem = $privateKey->toString('PKCS1');
        $publicPem  = $privateKey->getPublicKey()->toString('PKCS8');

        return [
            'private' => $privatePem,
            'public'  => $publicPem,
        ];
    }

    /**
     * 签名
     *
     * @param string $data
     * @param string $privatePem
     *
     * @return string
     */
    public static function sign(string $data, string $privatePem): string
    {
        $privateKey = PublicKeyLoader::loadPrivateKey($privatePem)
            ->withHash('sha256')
            ->withPadding(RSA::SIGNATURE_PKCS1);

        return $privateKey->sign($data);
    }

    /**
     * 验证签名
     *
     * @param string $data
     * @param string $signature
     * @param string $publicPem
     *
     * @return bool
     */
    public static function verify(string $data, string $signature, string $publicPem): bool
    {
        $publicKey = PublicKeyLoader::load($publicPem)
            ->withHash('sha256')
            ->withPadding(RSA::SIGNATURE_PKCS1);

        return $publicKey->verify($data, $signature);
    }

    /**
     * 公钥加密
     *
     * @param string $data
     * @param string $publicPem
     *
     * @return string
     */
    public static function encrypt(string $data, string $publicPem): string
    {
        $publicKey = PublicKeyLoader::load($publicPem)->withPadding(RSA::ENCRYPTION_PKCS1);
        return base64_encode($publicKey->encrypt($data));
    }

    /**
     * 私钥解密
     *
     * @param string $cipherBase64
     * @param string $privatePem
     *
     * @return string
     */
    public static function decrypt(string $cipherBase64, string $privatePem): string
    {
        $privateKey = PublicKeyLoader::loadPrivateKey($privatePem)
            ->withPadding(RSA::ENCRYPTION_PKCS1);

        $cipher = base64_decode($cipherBase64);
        return $privateKey->decrypt($cipher);
    }


    /**
     * 公钥掩码显示
     *
     * @param string $pem
     * @param int    $keep
     *
     * @return string
     */
    public static function maskKey(string $pem, int $keep = 20): string
    {
        $clean = str_replace(
            ["\r", "\n", "-----BEGIN PUBLIC KEY-----", "-----END PUBLIC KEY-----"],
            '',
            $pem
        );
        $len   = strlen($clean);
        return substr($clean, 0, $keep) . '...' . substr($clean, -$keep);
    }
}
