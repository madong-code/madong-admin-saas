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

use core\security\jwt\enum\ClientType;
use core\security\jwt\enum\LoginMode;
use core\security\jwt\enum\TokenStatus;
use core\security\jwt\enum\TokenType;
use core\security\jwt\ex\JwtException;
use core\security\jwt\ex\JwtRefreshTokenExpiredException;
use core\security\jwt\ex\JwtTokenExpiredException;
use core\security\jwt\ex\JwtTokenInBlacklistException;
use core\security\jwt\ex\JwtTokenInvalidException;
use core\security\jwt\interfaces\BlacklistStorageInterface;
use core\security\jwt\interfaces\TokenStorageInterface;
use core\security\jwt\storage\RedisTokenStorage;
use core\security\jwt\storage\RedisBlacklistStorage;
use core\business\tenant\context\TenantContext;
use Firebase\JWT\BeforeValidException;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\SignatureInvalidException;
use UnexpectedValueException;

class JwtToken
{
    /**
     * @var string 密钥
     */
    protected string $secret;

    /**
     * @var string 加密算法
     */
    protected string $algo;

    /**
     * @var array 配置
     */
    protected array $config;

    /**
     * @var TokenStorageInterface Token 存储
     */
    protected TokenStorageInterface $tokenStorage;

    /**
     * @var BlacklistStorageInterface 黑名单存储
     */
    protected BlacklistStorageInterface $blacklistStorage;

    /**
     * @var Token|null 当前 Token
     */
    protected ?Token $currentToken = null;

    /**
     * @var array|null 当前载荷缓存
     */
    protected ?array $payloadCache = null;

    public function __construct(
        ?TokenStorageInterface     $tokenStorage = null,
        ?BlacklistStorageInterface $blacklistStorage = null,
        array                      $config = []
    )
    {
        $this->config = $config ?: config('core.security.jwt', []);
        $this->secret = $this->config['secret'] ?? '';
        $this->algo   = $this->config['algo'] ?? 'HS256';

        if (empty($this->secret) || $this->secret === 'your-secret-key-change-in-production') {
            $this->secret = 'default-secret-key-for-development';
        }

        $this->tokenStorage     = $tokenStorage ?? new RedisTokenStorage($this->config);
        $this->blacklistStorage = $blacklistStorage ?? new RedisBlacklistStorage($this->config);
    }

    /**
     * 获取 Token 存储
     */
    public function getTokenStorage(): TokenStorageInterface
    {
        return $this->tokenStorage;
    }

    /**
     * 获取黑名单存储
     */
    public function getBlacklistStorage(): BlacklistStorageInterface
    {
        return $this->blacklistStorage;
    }

    /**
     * 生成 Token
     *
     * @param string      $id         用户ID（雪花ID，需用string）
     * @param string      $clientType 客户端类型（可扩展）
     * @param array       $extraData  额外数据
     * @param string|null $loginMode  登录模式（null 使用配置默认）
     *
     * @return Token
     * @throws \core\jwt2\ex\JwtException
     */
    public function generate(
        string  $id,
        string  $clientType,
        array   $extraData = [],
        ?string $loginMode = null
    ): Token
    {
        // 验证客户端类型
        if (!ClientType::isValid($clientType)) {
            throw new JwtException("Invalid client type: {$clientType}");
        }

        $loginMode = $loginMode ?? ($this->config['login_mode'] ?? LoginMode::MULTI->value);

        // 根据登录模式处理现有 Token
        $this->handleLoginMode($id, $clientType, $loginMode);

        // 生成 JTI
        $jti = $this->generateJti();

        // 生成 Access Token
        $accessTtl     = $this->config['ttl']['access'] ?? 7200;
        $accessPayload = $this->buildPayload(
            id: $id,
            client: $clientType,
            jti: $jti,
            type: TokenType::ACCESS,
            extra: $extraData
        );
        $accessToken   = $this->makeToken($accessPayload);

        // 生成 Refresh Token
        $refreshTtl     = $this->config['ttl']['refresh'] ?? 604800;
        $refreshJti     = $this->generateJti();
        $refreshPayload = $this->buildPayload(
            id: $id,
            client: $clientType,
            jti: $refreshJti,
            type: TokenType::REFRESH,
            extra: $extraData
        );
        $refreshToken   = $this->makeToken($refreshPayload);

        // 存储 Token 信息
        $tenantId = $extraData['current_tenant']['id'] ?? null;
        $tokenData = [
            'id'          => $id,
            'client_type' => $clientType,
            'access_jti'  => $jti,
            'refresh_jti' => $refreshJti,
            'extra'       => $extraData,
            'login_mode'  => $loginMode,
            'created_at'  => time(),
            'tenant_id'   => $tenantId, // 顶层租户标识，方便 Redis 查看
        ];
        // 用 access JTI 存储（供正常请求验证使用）
        $this->tokenStorage->save($jti, $tokenData, $accessTtl);
        // 同时用 refresh JTI 存储（供 refresh() 按 refresh_token 的 JTI 查找 extra 数据）
        // refresh() 中 $oldJti 来自 refresh_token 的 payload.jti，与这里的 $refreshJti 一致
        // refresh_token 不加入用户索引，避免 getSessions 出现重复记录
        $this->tokenStorage->save($refreshJti, $tokenData, $this->config['ttl']['refresh'] ?? 604800, false);

        // 创建 Token 对象
        $fullPayload = array_merge($accessPayload, ['id' => $id]);
        return Token::create($accessToken, $refreshToken, $accessTtl, $fullPayload);
    }

    /**
     * 刷新 Token
     *
     * @param string|null $refreshToken 刷新令牌（null 使用请求中的）
     *
     * @return Token
     * @throws \core\security\jwt\ex\JwtException
     * @throws \core\security\jwt\ex\JwtRefreshTokenExpiredException
     * @throws \core\security\jwt\ex\JwtTokenInBlacklistException
     */
    public function refresh(?string $refreshToken = null): Token
    {
        $token = $refreshToken ?? $this->getRefreshTokenFromRequest();

        if (empty($token)) {
            throw new JwtException('Refresh token is required');
        }

        // 解析 Token
        try {
            $payload = $this->parseToken($token);
        } catch (ExpiredException $e) {
            throw new JwtRefreshTokenExpiredException('Refresh token has expired');
        }

        // 验证 Token 类型
        if (($payload['type'] ?? '') !== TokenType::REFRESH->value) {
            throw new JwtException('Invalid token type for refresh');
        }

        $id         = (string)($payload['id'] ?? '');
        $clientType = $payload['client'] ?? '';
        $oldJti     = $payload['jti'] ?? '';

        if (empty($id)) {
            throw new JwtException('Invalid user in token');
        }

        // 获取原 Token 信息（优先从 Redis，确保数据最新）
        $oldTokenInfo = $this->tokenStorage->get($oldJti);

        // 检查刷新令牌是否在黑名单
        if ($this->blacklistStorage->has($oldJti)) {
            throw new JwtTokenInBlacklistException('Token has been revoked');
        }

        // 清理旧的 access_token（避免 user 索引和 jwt 存储残留孤儿记录）
        $oldAccessJti = $oldTokenInfo['access_jti'] ?? null;
        if ($oldAccessJti) {
            $this->tokenStorage->delete($oldAccessJti);
            $this->tokenStorage->removeFromUserIndex($id, $oldAccessJti);
            // 将旧 access_token 加入黑名单（防止 TTL 窗口内被重放）
            $this->blacklistStorage->add($oldAccessJti, $this->config['ttl']['access'] ?? 7200);
        }

        // 获取额外数据（Redis > JWT payload > 空数组）
        // 异地登录/切换租户/Redis重启后，extra 从 payload 回退确保 current_tenant 等关键信息不丢失
        $extraData = $oldTokenInfo['extra'] ?? $payload['extra'] ?? [];
        $loginMode = $this->config['login_mode'] ?? $oldTokenInfo['login_mode'] ?? LoginMode::MULTI->value;

        // 将旧 refresh_token 加入黑名单
        $this->blacklistStorage->add($oldJti, $this->config['ttl']['access'] ?? 7200);

        // 撤销旧的 refresh_token
        $this->tokenStorage->delete($oldJti);

        // 生成新 Token
        return $this->generate($id, $clientType, $extraData, $loginMode);
    }

    /**
     * 解析 Token
     *
     * @param string $token
     *
     * @return array
     */
    public function parse(string $token): array
    {
        return $this->parseToken($token);
    }

    /**
     * 获取当前用户
     *
     * @return array|null
     */
    public function user(): ?array
    {
        $payload = $this->getPayloadFromRequest();
        if (empty($payload)) {
            return null;
        }

        return [
            'id'          => (string)($payload['id'] ?? ''),
            'client_type' => $payload['client'] ?? '',
            'jti'         => $payload['jti'] ?? '',
            'extra'       => $payload['extra'] ?? [],
        ];
    }

    /**
     * 获取当前用户ID（雪花ID）
     */
    public function id(): ?string
    {
        $user = $this->user();
        return $user ? $user['id'] : null;
    }

    /**
     * 获取当前 Token
     */
    public function getToken(): ?Token
    {
        if ($this->currentToken !== null) {
            return $this->currentToken;
        }

        $payload = $this->getPayloadFromRequest();
        if (empty($payload)) {
            return null;
        }

        $accessToken = $this->getAccessTokenFromRequest();
        if (empty($accessToken)) {
            return null;
        }

        $accessTtl          = $this->config['ttl']['access'] ?? 7200;
        $this->currentToken = Token::create($accessToken, '', $accessTtl, $payload);

        return $this->currentToken;
    }

    /**
     * 验证 Token
     *
     * @param string|null $token
     *
     * @return array|null
     * @throws \core\security\jwt\ex\JwtException
     * @throws \core\security\jwt\ex\JwtTokenExpiredException
     * @throws \core\security\jwt\ex\JwtTokenInBlacklistException
     * @throws \core\security\jwt\ex\JwtTokenInvalidException
     */
    public function verify(?string $token = null): ?array
    {
        $token = $token ?? $this->getAccessTokenFromRequest();

        if (empty($token)) {
            return null;
        }

        try {
            $payload = $this->parseToken($token);

            // 检查是否在黑名单
            $jti = $payload['jti'] ?? '';
            if (!empty($jti) && $this->blacklistStorage->has($jti)) {
                throw new JwtTokenInBlacklistException('Token has been revoked');
            }

            // 检查 Token 类型
            if (($payload['type'] ?? '') !== TokenType::ACCESS->value) {
                throw new JwtException('Invalid token type');
            }

            // 检查用户是否存在
            $id = (string)($payload['id'] ?? '');
            if (empty($id)) {
                throw new JwtException('Invalid user in token');
            }

            return $payload;
        } catch (ExpiredException $e) {
            throw new JwtTokenExpiredException('Token has expired');
        } catch (SignatureInvalidException $e) {
            throw new JwtTokenInvalidException('Invalid token signature');
        } catch (BeforeValidException $e) {
            throw new JwtTokenInvalidException('Token not yet valid');
        } catch (UnexpectedValueException $e) {
            throw new JwtTokenInvalidException('Invalid token format');
        }
    }

    /**
     * 退出登录（当前设备）
     *
     * @param string|null $token
     *
     * @return bool
     */
    public function logout(?string $token = null): bool
    {
        $token = $token ?? $this->getAccessTokenFromRequest();

        if (empty($token)) {
            return false;
        }

        try {
            $payload = $this->parseToken($token);
            $jti     = $payload['jti'] ?? '';
            $id      = $payload['id'] ?? '';

            if (!empty($jti)) {
                // 黑名单 TTL 与 logoutAll 一致，使用 refresh_ttl 确保覆盖整个刷新周期
                $blacklistTtl = $this->config['ttl']['refresh'] ?? 604800;

                // 将 access_token 加入黑名单，防止退出后被复用
                $this->blacklistStorage->add($jti, $blacklistTtl);

                // 获取 token 存储信息
                $tokenInfo = $this->tokenStorage->get($jti);
                if ($tokenInfo && !empty($tokenInfo['refresh_jti'])) {
                    // 将 refresh_token 也加入黑名单，防止被复用刷新
                    $this->blacklistStorage->add($tokenInfo['refresh_jti'], $blacklistTtl);
                    $this->tokenStorage->delete($tokenInfo['refresh_jti']);
                }

                // 删除 access_token 存储
                $this->tokenStorage->delete($jti);

                // 从用户索引中移除，避免 user:1 Hash 残留孤儿 JTI
                if (!empty($id)) {
                    $this->tokenStorage->removeFromUserIndex((string)$id, $jti);
                }
            }

            return true;
        } catch (\Throwable $e) {
            // 记录日志便于排查，不能静默吞异常导致安全漏洞
            $this->logError('JWT logout failed', [
                'error' => $e->getMessage(),
                'token_prefix' => substr($token, 0, 20) . '...',
            ]);
            return false;
        }
    }

    /**
     * 记录错误日志（安全敏感操作，任何失败都应可追踪）
     */
    protected function logError(string $message, array $context = []): void
    {
        try {
            \core\infrastructure\logger\Logger::error($message, $context);
        } catch (\Throwable) {
            // 日志系统自身异常不影响主流程
        }
    }

    /**
     * 退出所有设备
     *
     * @param string      $id
     * @param string|null $clientType
     *
     * @return int 退出设备数
     */
    /**
     * 退出所有设备
     *
     * @param string      $id
     * @param string|null $clientType
     *
     * @return int
     */
    public function logoutAll(string $id, ?string $clientType = null): int
    {
        $tenantId = $this->resolveTenantId();

        // 获取用户的所有 token 信息
        $tokens = $this->tokenStorage->getUserTokens($id, $clientType, $tenantId);

        if (empty($tokens)) {
            return 0;
        }

        $count = 0;
        // 将所有 token 加入黑名单并删除
        foreach ($tokens as $jti => $info) {
            // 将 access_token 加入黑名单（使用 refresh_ttl 确保足够长）
            $blacklistTtl = $this->config['ttl']['refresh'] ?? 604800;
            $this->blacklistStorage->add($jti, $blacklistTtl);

            // 将 refresh_token 也加入黑名单
            if (!empty($info['refresh_jti'])) {
                $this->blacklistStorage->add($info['refresh_jti'], $blacklistTtl);
                // 删除 refresh_token 存储
                $this->tokenStorage->delete($info['refresh_jti']);
            }

            // 删除 access_token 存储
            $this->tokenStorage->delete($jti);
            $count++;
        }

        // 清理用户索引（deleteByUser 只会清理索引，不会删除已删除的 token）
        $this->tokenStorage->deleteByUser($id, $clientType, null, $tenantId);

        return $count;
    }

    /**
     * 退出其他设备（保留当前）
     *
     * @param string|null $id
     * @param string|null $clientType
     * @param string|null $exceptJti 保留的 JTI
     *
     * @return int 退出设备数
     */
    public function logoutOthers(?string $id = null, ?string $clientType = null, ?string $exceptJti = null): int
    {
        $id = $id ?? $this->id();
        if ($id === null) {
            return 0;
        }

        $exceptJti = $exceptJti ?? ($this->getPayloadFromRequest()['jti'] ?? null);
        $tenantId  = $this->resolveTenantId();

        // 获取用户的所有 token 信息
        $tokens = $this->tokenStorage->getUserTokens($id, $clientType, $tenantId);

        if (empty($tokens)) {
            return 0;
        }

        $blacklistTtl = $this->config['ttl']['refresh'] ?? 604800;
        $count        = 0;

        // 遍历并处理需要删除的 token
        foreach ($tokens as $jti => $info) {
            // 跳过保留的 JTI
            if ($exceptJti !== null && $jti === $exceptJti) {
                continue;
            }

            // 将 access_token 加入黑名单
            $this->blacklistStorage->add($jti, $blacklistTtl);

            // 将 refresh_token 加入黑名单并删除
            if (!empty($info['refresh_jti'])) {
                $this->blacklistStorage->add($info['refresh_jti'], $blacklistTtl);
                $this->tokenStorage->delete($info['refresh_jti']);
            }

            // 删除 access_token 存储
            $this->tokenStorage->delete($jti);
            $count++;
        }

        // 清理用户索引
        $this->tokenStorage->deleteByUser($id, $clientType, $exceptJti, $tenantId);

        return $count;
    }

    /**
     * 获取用户的所有会话列表
     *
     * @param string      $id
     * @param string|null $clientType
     *
     * @return array
     */
    public function getSessions(string $id, ?string $clientType = null): array
    {
        $tenantId = $this->resolveTenantId();

        // 使用 tokenStorage 获取用户的所有 token 信息
        $tokens = $this->tokenStorage->getUserTokens($id, $clientType, $tenantId);

        if (empty($tokens)) {
            return [];
        }

        $sessions = [];
        foreach ($tokens as $jti => $info) {
            // 获取完整的 token 数据
            $tokenData = $this->tokenStorage->get($jti);
            if ($tokenData !== null) {
                $sessions[] = [
                    'jti'         => $jti,
                    'client_type' => $info['client_type'] ?? $tokenData['client_type'] ?? '',
                    'created_at'  => $info['created_at'] ?? $tokenData['created_at'] ?? 0,
                    'extra'       => $tokenData['extra'] ?? [],
                ];
            }
        }

        return $sessions;
    }

    /**
     * 通过 JTI 踢出指定会话
     *
     * @param string $jti
     *
     * @return bool
     */
    public function kickoutByJti(string $jti): bool
    {
        // 获取 token 信息
        $tokenData = $this->tokenStorage->get($jti);

        if ($tokenData === null) {
            return false;
        }

        // 将 access_token 加入黑名单
        $blacklistTtl = $this->config['ttl']['refresh'] ?? 604800;
        $this->blacklistStorage->add($jti, $blacklistTtl);

        // 将 refresh_token 也加入黑名单
        $refreshJti = $tokenData['refresh_jti'] ?? '';
        if (!empty($refreshJti)) {
            $this->blacklistStorage->add($refreshJti, $blacklistTtl);
            $this->tokenStorage->delete($refreshJti);
        }

        // 删除 access_token 存储
        $this->tokenStorage->delete($jti);

        // 删除用户索引中的记录
        $userId = $tokenData['id'] ?? '';
        if (!empty($userId)) {
            $userKey = $this->config['storage']['redis_prefix'] ?? 'jwt:';
            $userKey .= 'user:' . $userId;
            \support\Redis::hdel($userKey, $jti);
        }

        return true;
    }

    /**
     * 获取登录设备数
     *
     * @param string      $id
     * @param string|null $clientType
     *
     * @return int
     */
    public function deviceCount(string $id, ?string $clientType = null): int
    {
        $tenantId = $this->resolveTenantId();
        return $this->tokenStorage->countByUser($id, $clientType, $tenantId);
    }

    /**
     * 检查用户是否在黑名单
     *
     * @param string $id
     *
     * @return bool
     */
    public function isUserBlacklisted(string $id): bool
    {
        return $this->blacklistStorage->hasUser($id);
    }

    /**
     * 将用户加入黑名单
     *
     * @param string   $id
     * @param int|null $ttl 有效期（秒）
     *
     * @return bool
     */
    public function blacklistUser(string $id, ?int $ttl = null): bool
    {
        $ttl = $ttl ?? ($this->config['ttl']['refresh'] ?? 604800);

        // 退出用户所有设备
        $this->logoutAll($id);

        // 加入黑名单
        return $this->blacklistStorage->addUser($id, $ttl);
    }

    /**
     * 撤销 Token
     *
     * @param string $token
     *
     * @return bool
     */
    public function revoke(string $token): bool
    {
        try {
            $payload = $this->parseToken($token);
            $jti     = $payload['jti'] ?? '';

            if (!empty($jti)) {
                $this->blacklistStorage->add($jti, $this->config['ttl']['access'] ?? 7200);
                $this->tokenStorage->delete($jti);
                return true;
            }

            return false;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * 获取 Token 状态
     *
     * @param string $token
     *
     * @return TokenStatus
     */
    public function getTokenStatus(string $token): TokenStatus
    {
        try {
            $payload = $this->parseToken($token);
            $jti     = $payload['jti'] ?? '';

            // 检查是否在黑名单
            if (!empty($jti) && $this->blacklistStorage->has($jti)) {
                return TokenStatus::REVOKED;
            }

            // 检查是否过期
            $exp = $payload['exp'] ?? 0;
            if ($exp > 0 && $exp < time()) {
                return TokenStatus::EXPIRED;
            }

            return TokenStatus::ACTIVE;
        } catch (ExpiredException $e) {
            return TokenStatus::EXPIRED;
        } catch (\Throwable $e) {
            return TokenStatus::REVOKED;
        }
    }

    // ==================== 私有方法 ====================

    /**
     * 处理登录模式
     */
    protected function handleLoginMode(string $id, string $clientType, string $loginMode): void
    {
        switch ($loginMode) {
            case LoginMode::SINGLE->value:
                // 单端登录：踢掉所有现有设备
                $this->logoutAll($id);
                break;

            case LoginMode::CLIENT->value:
                // 客户端模式：踢掉同类型设备的登录
                $this->logoutAll($id, $clientType);
                break;

            case LoginMode::MULTI->value:
                // 多端登录：检查最大设备数
                $maxDevices = $this->config['security']['max_devices'] ?? 0;
                if ($maxDevices > 0) {
                    $tenantId = $this->resolveTenantId();
                    $count    = $this->tokenStorage->countByUser($id, null, $tenantId);
                    if ($count >= $maxDevices) {
                        // 删除最早的设备
                        $this->tokenStorage->deleteOldest($id, $tenantId);
                    }
                }
                break;
        }
    }

    /**
     * 构建 Token 载荷
     */
    protected function buildPayload(
        string    $id,
        string    $client,
        string    $jti,
        TokenType $type,
        array     $extra = []
    ): array
    {
        $now = time();
        $ttl = match ($type) {
            TokenType::ACCESS => $this->config['ttl']['access'] ?? 7200,
            TokenType::REFRESH => $this->config['ttl']['refresh'] ?? 604800,
        };

        $payload = [
            'iss'    => $this->config['issuer'] ?? 'webman',
            'id'     => $id,
            'client' => $client,
            'jti'    => $jti,
            'type'   => $type->value,
            'iat'    => $now,
            'exp'    => $now + $ttl,
        ];

        if (!empty($extra)) {
            $payload['extra'] = $extra;
        }

        return $payload;
    }

    /**
     * 生成 JWT Token
     */
    protected function makeToken(array $payload): string
    {
        return JWT::encode($payload, $this->secret, $this->algo);
    }

    /**
     * 解析 Token
     */
    protected function parseToken(string $token): array
    {
        // 处理带有前缀的 token
        if (str_starts_with($token, 'Bearer ')) {
            $token = substr($token, 7);
        }
        // JWT::decode 返回 stdClass，递归转数组避免嵌套对象问题
        return json_decode(json_encode(JWT::decode($token, new Key($this->secret, $this->algo))), true);
    }

    /**
     * 生成 JTI
     */
    protected function generateJti(): string
    {
        return bin2hex(random_bytes(16));
    }

    /**
     * 从请求中获取 Access Token
     */
    public function getAccessTokenFromRequest(): ?string
    {
        $auth = request()->header('Authorization', '');
        if (str_starts_with($auth, 'Bearer ')) {
            return substr($auth, 7);
        }
        return request()->input('token');
    }

    /**
     * 从请求中获取 Refresh Token
     */
    protected function getRefreshTokenFromRequest(): ?string
    {
        // 先从 Authorization 头中获取
        $tokenName     = config('core.security.jwt.token_name', 'Authorization');
        $authorization = request()->header($tokenName);
        if (!empty($authorization)) {
            if (str_starts_with($authorization, 'Bearer ')) {
                $token = substr($authorization, 7);
                if (!empty($token)) {
                    return $token;
                }
            }
        }

        // 再从请求输入中获取
        return request()->input('refresh_token');
    }

    /**
     * 从请求中获取 Token 载荷
     */
    public function getPayloadFromRequest(): array
    {
        if ($this->payloadCache !== null) {
            return $this->payloadCache;
        }

        try {
            $token = $this->getAccessTokenFromRequest();
            if (empty($token)) {
                return [];
            }

            $this->payloadCache = $this->parseToken($token);
            return $this->payloadCache;
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * 解析当前请求的租户 ID，用于 Token 操作的租户隔离
     *
     * 单体模式  → 返回 null（不过滤）
     * 多租户 + 超管无上下文 → 返回 null（不过滤）
     * 多租户 + 有租户上下文 → 返回当前租户 ID
     *
     * @return int|string|null
     */
    protected function resolveTenantId(): int|string|null
    {
        if (!TenantContext::isTenantEnabled() || !TenantContext::hasTenant()) {
            return null;
        }
        return TenantContext::getTenantId();
    }
}
