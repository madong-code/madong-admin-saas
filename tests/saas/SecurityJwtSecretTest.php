<?php
declare(strict_types=1);

use core\security\jwt\ex\JwtException;
use core\security\jwt\ex\JwtTokenInBlacklistException;
use core\security\jwt\ex\JwtTokenInvalidException;
use core\security\jwt\interfaces\BlacklistStorageInterface;
use core\security\jwt\interfaces\TokenStorageInterface;
use core\security\jwt\JwtSecret;
use core\security\jwt\JwtToken;
use Firebase\JWT\JWT;
use PHPUnit\Framework\TestCase;

/**
 * JWT 硬编码回退密钥（CWE-321 / CWE-798）回归用例
 */
final class SecurityJwtSecretTest extends TestCase
{
    private const SECRET = 'test-only-secret-0123456789abcdefghijklmnop';
    private const PUBLIC_FALLBACK_SECRET = 'default-secret-key-for-development';

    private function makeJwt(
        array $config = [],
        ?TokenStorageInterface $tokenStorage = null,
        ?BlacklistStorageInterface $blacklistStorage = null
    ): JwtToken {
        return new JwtToken(
            $tokenStorage ?? $this->createMock(TokenStorageInterface::class),
            $blacklistStorage ?? $this->createMock(BlacklistStorageInterface::class),
            $config ?: ['secret' => self::SECRET, 'algo' => 'HS256']
        );
    }

    private function sign(array $payload, string $secret): string
    {
        return JWT::encode($payload, $secret, 'HS256');
    }

    private function validPayload(): array
    {
        return [
            'id'   => '1',
            'jti'  => bin2hex(random_bytes(8)),
            'type' => 'access',
            'iat'  => time(),
            'exp'  => time() + 3600,
        ];
    }

    public function testMissingSecretFailsFast(): void
    {
        $this->expectException(JwtException::class);
        $this->makeJwt(['secret' => '', 'algo' => 'HS256']);
    }

    public function testConfigPlaceholderFailsFast(): void
    {
        $this->expectException(JwtException::class);
        $this->makeJwt(['secret' => 'your-secret-key-change-in-production', 'algo' => 'HS256']);
    }

    public function testPublicFallbackSecretFailsFast(): void
    {
        $this->expectException(JwtException::class);
        $this->makeJwt(['secret' => self::PUBLIC_FALLBACK_SECRET, 'algo' => 'HS256']);
    }

    public function testShortSecretFailsFast(): void
    {
        $this->expectException(JwtException::class);
        $this->makeJwt(['secret' => 'short', 'algo' => 'HS256']);
    }

    public function testForgedTokenSignedWithPublicFallbackKeyIsRejected(): void
    {
        $forged = $this->sign($this->validPayload(), self::PUBLIC_FALLBACK_SECRET);

        $this->expectException(JwtTokenInvalidException::class);
        $this->makeJwt()->verify($forged);
    }

    public function testBlacklistedTokenIsRejected(): void
    {
        $blacklist = $this->createMock(BlacklistStorageInterface::class);
        $blacklist->method('has')->willReturn(true);

        $this->expectException(JwtTokenInBlacklistException::class);
        $this->makeJwt([], null, $blacklist)->verify($this->sign($this->validPayload(), self::SECRET));
    }

    public function testValidTokenPassesVerify(): void
    {
        $payload = $this->makeJwt()->verify($this->sign($this->validPayload(), self::SECRET));
        self::assertSame('1', (string)$payload['id']);
    }

    public function testGeneratedSecretIsStrongAndRandom(): void
    {
        $first  = JwtSecret::generate();
        $second = JwtSecret::generate();

        self::assertSame(64, strlen($first));
        self::assertNotSame($first, $second);
        self::assertTrue(JwtSecret::isValid($first));
        self::assertFalse(JwtSecret::isValid(self::PUBLIC_FALLBACK_SECRET));
    }

    public function testEnsureInEnvGeneratesAndIsIdempotent(): void
    {
        $first = JwtSecret::ensureInEnv("APP_DEBUG=false\nJWT_SECRET=\n");
        self::assertTrue($first['changed']);
        self::assertMatchesRegularExpression('/^JWT_SECRET=[0-9a-f]{64}$/m', $first['content']);

        $second = JwtSecret::ensureInEnv($first['content']);
        self::assertFalse($second['changed']);
        self::assertSame($first['content'], $second['content']);
    }

    public function testEnsureInEnvReplacesPlaceholderAndForceRotates(): void
    {
        $repaired = JwtSecret::ensureInEnv("JWT_SECRET=your-secret-key-change-in-production\n");
        self::assertTrue($repaired['changed']);
        self::assertStringNotContainsString('your-secret-key-change-in-production', $repaired['content']);

        preg_match('/^JWT_SECRET=(.*)$/m', $repaired['content'], $before);
        $rotated = JwtSecret::ensureInEnv($repaired['content'], true);
        preg_match('/^JWT_SECRET=(.*)$/m', $rotated['content'], $after);

        self::assertTrue($rotated['changed']);
        self::assertNotSame($before[1], $after[1]);
    }

    public function testExampleEnvAndConfigContainNoFixedDefault(): void
    {
        $root = dirname(__DIR__, 2);
        $exampleEnv = (string)file_get_contents($root . DIRECTORY_SEPARATOR . '.example.env');
        $jwtConfig  = (string)file_get_contents($root . DIRECTORY_SEPARATOR . 'core/security/config/jwt.php');
        $jwtToken   = (string)file_get_contents($root . DIRECTORY_SEPARATOR . 'core/security/jwt/JwtToken.php');

        self::assertStringContainsString('JWT_SECRET=', $exampleEnv);
        self::assertStringNotContainsString('your-secret-key-change-in-production', $jwtConfig);
        self::assertStringNotContainsString("'default-secret-key-for-development'", $jwtToken);
    }
}
