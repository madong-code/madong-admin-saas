<?php
declare(strict_types=1);

namespace Tests\Support;

use core\security\jwt\interfaces\BlacklistStorageInterface;
use core\security\jwt\interfaces\TokenStorageInterface;
use core\security\jwt\JwtToken;

/**
 * 注入指定 Token 的测试替身，避免依赖 Webman 请求上下文与 Redis。
 */
final class TestJwtToken extends JwtToken
{
    public function __construct(
        array $config,
        TokenStorageInterface $tokenStorage,
        BlacklistStorageInterface $blacklistStorage,
        private readonly string $token
    ) {
        parent::__construct($tokenStorage, $blacklistStorage, $config);
    }

    public function getAccessTokenFromRequest(): ?string
    {
        return $this->token;
    }
}
