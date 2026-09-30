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
namespace core\security\jwt;

use DateTimeImmutable;
use JsonSerializable;

/**
 * Token 数据对象
 */
readonly class Token implements JsonSerializable
{
    /**
     * @param string $accessToken 访问令牌
     * @param string $refreshToken 刷新令牌
     * @param int $expiresIn 有效期（秒）
     * @param string $tokenType 令牌类型
     * @param string $jti 令牌唯一标识
     * @param string $id 用户ID（雪花ID）
     * @param string $clientType 客户端类型
     * @param DateTimeImmutable $issuedAt 签发时间
     * @param DateTimeImmutable $expiresAt 过期时间
     * @param array $payload 载荷数据
     */
    public function __construct(
        public string            $accessToken,
        public string            $refreshToken,
        public int               $expiresIn,
        public string            $tokenType = 'Bearer',
        public string            $jti = '',
        public string            $id = '',
        public string            $clientType = '',
        public DateTimeImmutable $issuedAt = new DateTimeImmutable(),
        public DateTimeImmutable $expiresAt = new DateTimeImmutable(),
        public array             $payload = [],
    ) {}

    /**
     * 创建 Token 实例
     *
     * @param string $accessToken 访问令牌
     * @param string $refreshToken 刷新令牌
     * @param int $expiresIn 有效期（秒）
     * @param array $payload 完整载荷
     * @return static
     */
    public static function create(
        string $accessToken,
        string $refreshToken,
        int $expiresIn,
        array $payload = []
    ): static {
        $now = new DateTimeImmutable();
        $expiresAt = (new DateTimeImmutable())->setTimestamp($now->getTimestamp() + $expiresIn);

        return new static(
            accessToken: $accessToken,
            refreshToken: $refreshToken,
            expiresIn: $expiresIn,
            jti: $payload['jti'] ?? '',
            id: (string)($payload['id'] ?? ''),
            clientType: $payload['client'] ?? '',
            issuedAt: $now,
            expiresAt: $expiresAt,
            payload: $payload,
        );
    }

    /**
     * 从请求中解析 Token
     *
     * 支持多种格式：
     * 1. Header: Authorization: Bearer <token>
     * 2. Header: Authorization: <token>
     * 3. 参数: access_token=<token>
     * 4. 参数: token=<token>
     *
     * @return string|null
     */
    public static function fromRequest(): ?string
    {
        // 1. Header Authorization Bearer
        $auth = request()->header('Authorization', '');
        if (!empty($auth)) {
            if (str_starts_with($auth, 'Bearer ')) {
                return substr($auth, 7);
            }
            // 直接是 token
            return $auth;
        }

        // 2. 参数 access_token
        $token = request()->input('access_token');
        if (!empty($token)) {
            return $token;
        }

        // 3. 参数 token
        $token = request()->input('token');
        if (!empty($token)) {
            return $token;
        }

        return null;
    }

    /**
     * 解析 Token 字符串
     *
     * @param string $tokenString JWT token 字符串
     * @param int $expiresIn 有效期（秒）
     * @param array $payload 载荷数据
     * @return static
     */
    public static function parse(string $tokenString, int $expiresIn, array $payload = []): static
    {
        return static::create($tokenString, '', $expiresIn, $payload);
    }

    /**
     * 获取访问令牌
     */
    public function getAccessToken(): string
    {
        return $this->accessToken;
    }

    /**
     * 获取刷新令牌
     */
    public function getRefreshToken(): string
    {
        return $this->refreshToken;
    }

    /**
     * 获取用户ID（雪花ID）
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * 获取客户端类型
     */
    public function getClientType(): string
    {
        return $this->clientType;
    }

    /**
     * 获取令牌唯一标识
     */
    public function getJti(): string
    {
        return $this->jti;
    }

    /**
     * 获取过期时间戳
     */
    public function getExpiresAt(): int
    {
        return $this->expiresAt->getTimestamp();
    }

    /**
     * 检查是否已过期
     */
    public function isExpired(): bool
    {
        return time() >= $this->expiresAt->getTimestamp();
    }

    /**
     * 获取剩余有效期（秒）
     */
    public function getRemainingTime(): int
    {
        $remaining = $this->expiresAt->getTimestamp() - time();
        return max(0, $remaining);
    }

    /**
     * 转换为数组
     */
    public function toArray(): array
    {
        return [
            'token_type' => $this->tokenType,
            'access_token' => $this->accessToken,
            'refresh_token' => $this->refreshToken,
            'expires_in' => $this->expiresIn,
            'expires_at' => $this->expiresAt->format('Y-m-d H:i:s'),
            'jti' => $this->jti,
            'id' => $this->id,
            'client_type' => $this->clientType,
            'issued_at' => $this->issuedAt->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * 转换为前端格式
     */
    public function toFrontend(): array
    {
        return [
            'token' => $this->accessToken,
            'refresh_token' => $this->refreshToken,
            'expires_in' => $this->expiresIn,
            'expires_at' => $this->expiresAt->getTimestamp(),
            'token_type' => $this->tokenType,
        ];
    }

    /**
     * JSON 序列化
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
