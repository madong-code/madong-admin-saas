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

namespace app\api;

use app\model\member\Member;
use app\service\api\member\MemberService;
use app\service\admin\member\MemberAuthService;
use core\infrastructure\cache\CacheService;
use core\foundation\exception\handler\ForbiddenHttpException;
use core\security\jwt\JwtToken;
use core\infrastructure\logger\Logger;
use madong\swagger\attribute\Permission;

/**
 * 会员权限类（向后兼容的封装）
 * 注意：这个类是为了保持向后兼容性而存在
 * 新的代码应该使用 CurrentMenber 类（包含权限功能）
 */
final class CurrentMember
{
    // 缓存键前缀
    public const CACHE_PREFIX = 'member_user';

    // 缓存过期时间（秒）
    public const CACHE_EXPIRE = 3600;

    private MemberService $service;
    private MemberAuthService $authService;
    private CacheService $cache;

    public function __construct(
        MemberService     $service,
        MemberAuthService $authService,
        CacheService      $cache
    )
    {
        $this->service     = $service;
        $this->authService = $authService;
        $this->cache       = $cache;
    }

    /**
     * 生成会员缓存键
     *
     * @param int|string $uid 会员ID
     *
     * @return string 缓存键
     */
    public static function generateCacheKey(int|string $uid): string
    {
        return self::CACHE_PREFIX . '_' . $uid;
    }

    public function user(bool $toArray = false): null|Member|array
    {
        if (!$this->getToken()) {
            return null;
        }
        $uid      = $this->id();
        $cacheKey = self::generateCacheKey($uid);

        // 使用缓存服务获取会员信息
        $member = $this->cache->remember($cacheKey, function () use ($uid) {
            return $this->service->get($uid, ['*'], ['level', 'tags'], '', []);
        }, self::CACHE_EXPIRE);

        return $toArray && $member ? $member->toArray() : $member;
    }

    /**
     * 清除会员缓存
     *
     * @param int|string|null $uid 会员ID，默认清除当前用户缓存
     */
    public function clearCache(int|string|null $uid = null): void
    {
        $uid = $uid ?? $this->id();
        if ($uid) {
            $cacheKey = self::generateCacheKey($uid);
            $this->cache->delete($cacheKey);

            // 同时清除权限缓存
            $permissionCacheKey = $cacheKey . '_permissions';
            $this->cache->delete($permissionCacheKey);
        }
    }

    public function refresh(): array
    {
        if (!$this->getToken()) {
            Logger::debug("当前会员无有效 Token，无法刷新", []);
            return [];
        }
        return (new JwtToken())->refresh()->toArray();
    }

    public function id(): int|string
    {
        $token = $this->getToken();
        if (!$token) {
            return 0;
        }
        try {
            $payload = (new JwtToken())->parse($token);
            $mid     = $payload['id'] ?? null;
            return $mid !== null ? $mid : 0;
        } catch (\Throwable $e) {
            Logger::error('CurrentMember::id() JWT 解析失败: ' . $e->getMessage(), ['token_prefix' => substr($token, 0, 10) . '...']);
            return 0;
        }
    }

    public function getToken(): ?string
    {
        $request = request();
        if (empty($request)) {
            return null;
        }
        $tokenName     = config('core.security.jwt.token_name', 'Authorization');
        $authorization = $request->header($tokenName);
        if (empty($authorization) || $authorization === 'undefined') {
            $authorization = $request->get('token');
        }
        if (!$authorization || $authorization === 'undefined') {
            return null;
        }
        if (count(explode(' ', $authorization)) !== 2) {
            return null;
        }

        [$type, $token] = explode(' ', $authorization);

        if ($type !== 'Bearer') {
            return null;
        }

        if (!$token || $token === 'undefined') {
            return null;
        }

        return $token;
    }

    /**
     * 生成新令牌
     * @param array $userInfo 用户信息
     * @param string $type 类型
     * @return array 令牌信息
     */
    public function generateToken(array $userInfo, string $type = 'api'): array
    {
        $jwt = new JwtToken();
        $tokenObj = $jwt->generate((string)$this->id(), $type, $userInfo);
        return [
            'access_token' => $tokenObj->accessToken,
            'refresh_token' => $tokenObj->refreshToken,
            'expires_in' => $tokenObj->expiresIn,
            'expires_time' => time() + $tokenObj->expiresIn
        ];
    }

    /**
     * 登出（将令牌加入黑名单）
     * @param string|null $token 令牌，默认使用当前请求的令牌
     * @return bool 是否成功
     */
    public function logout(?string $token = null): bool
    {
        $token = $token ?? $this->getToken();
        if (!$token) {
            return false;
        }
        return (new JwtToken())->logout($token);
    }

    /**
     * 获取令牌负载信息
     * @return array 负载信息
     */
    public function getPayload(): array
    {
        try {
            $jwt = new JwtToken();
            // 使用 getPayloadFromRequest() 方法获取负载信息
            $payload = $jwt->getPayloadFromRequest();
            return $payload['extra'] ?? $payload;
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * 获取会员所有菜单权限码
     *
     * @return array 权限码数组
     */
    public function getPermissions(): array
    {
        $member = $this->user();
        if (!$member) {
            return [];
        }

        $uid      = $this->id();
        $cacheKey = self::generateCacheKey($uid) . '_permissions';

        // 使用缓存服务获取权限信息
        return $this->cache->remember($cacheKey, function () use ($member) {
            // 通过标签获取菜单权限码
            $permissions = [];
            $tags        = $member->tags()->with('permissions')->where('enabled', 1)->get();

            foreach ($tags as $tag) {
                foreach ($tag->permissions as $permission) {
                    if (!empty($permission->code)) {
                        $permissions[] = $permission->code;
                    }
                }
            }

            return array_unique($permissions);
        }, self::CACHE_EXPIRE);
    }

    /**
     * 检查会员是否有权限
     *
     * @param string|array $codes     权限码，可以是字符串或数组
     * @param string       $operation 操作类型，支持 'and' 或 'or'
     *
     * @return bool 是否有权限
     */
    public function hasPermission(string|array $codes, string $operation = 'and'): bool
    {
        // 如果是超级会员（可以根据需求扩展，例如会员等级等），直接返回true
        // 暂时不实现超级会员逻辑

        $codes             = is_array($codes) ? $codes : [$codes];
        $memberPermissions = $this->getPermissions();

        $operation = strtolower($operation);

        if ($operation === Permission::OPERATION_AND) {
            // AND模式：所有权限码都必须有
            foreach ($codes as $code) {
                if (!in_array($code, $memberPermissions)) {
                    return false;
                }
            }
            return true;
        } else if ($operation === Permission::OPERATION_OR) {
            // OR模式：至少有一个权限码
            foreach ($codes as $code) {
                if (in_array($code, $memberPermissions)) {
                    return true;
                }
            }
            return false;
        }

        throw new \InvalidArgumentException("不支持的操作类型: {$operation}");
    }

    /**
     * 检查会员是否有权限，没有权限时抛出异常
     * 注意：这个功能暂时不套用中间件，仅作为工具方法使用
     *
     * @param string|array $codes     权限码，可以是字符串或数组
     * @param string       $operation 操作类型，支持 'and' 或 'or'
     *
     * @throws ForbiddenHttpException 没有权限时抛出异常
     */
    public function checkPermission(string|array $codes, string $operation = 'and'): void
    {
        if (!$this->hasPermission($codes, $operation)) {
            $codes    = is_array($codes) ? $codes : [$codes];
            $codesStr = implode($operation === 'and' ? ',' : '或', $codes);
            throw new ForbiddenHttpException("缺少权限: {$codesStr}");
        }
    }

    /**
     * 获取会员的所有菜单（基于标签权限）
     *
     * @param bool $includeEmptyCode 是否包含权限码为空的菜单（不需要标签权限的公共菜单）
     *
     * @return \Illuminate\Database\Eloquent\Collection|null
     * @throws \Exception
     */
    public function getMenus(bool $includeEmptyCode = false): ?\Illuminate\Database\Eloquent\Collection
    {
        $uid = $this->id();
        if (!$uid) {
            return null;
        }

        return $this->authService->getMenusByMemberTags($uid, $includeEmptyCode);
    }
}