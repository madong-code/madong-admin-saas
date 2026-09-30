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

namespace app\platform;

use app\model\system\admin\Admin;
use app\scope\global\AccessPermissionScope;
use app\scope\global\TenantScope;
use core\infrastructure\cache\CacheService;
use core\foundation\exception\handler\ForbiddenHttpException;
use core\security\jwt\JwtToken;
use core\infrastructure\logger\Logger;
use madong\swagger\attribute\Permission;

/**
 * 平台端（platformapi）当前用户上下文
 *
 * 与 adminapi CurrentUser 的区别：
 * - 直接使用 Admin 模型查询（绕过 TenantScope）
 * - 不涉及 database 隔离模式的连接切换逻辑
 * - 缓存前缀独立（platform_user）
 * - 平台超管始终查主库，无需 isPlatformSuper → getAdminFromMainDb 分支
 */
final readonly class PlatformCurrentUser
{
    // 缓存键前缀
    public const CACHE_PREFIX = 'platform_user';

    // 缓存过期时间（秒）
    public const CACHE_EXPIRE = 3600;

    private CacheService $cache;

    public function __construct(CacheService $cache)
    {
        $this->cache = $cache;
    }

    /**
     * 生成管理员缓存键
     */
    public static function generateCacheKey(int|string $uid): string
    {
        return self::CACHE_PREFIX . '_' . $uid;
    }

    /**
     * 获取当前管理员信息（平台端：始终查主库，绕过 TenantScope）
     */
    public function admin(bool $toArray = false): null|Admin|array
    {
        if (!$this->getToken()) {
            return null;
        }

        $uid = $this->id();
        $cacheKey = self::generateCacheKey($uid);

        $admin = $this->cache->remember($cacheKey, function () use ($uid) {
            // 绕过 TenantScope 和 AccessPermissionScope 直接查库
            return Admin::withoutGlobalScope(TenantScope::class)
                ->withoutGlobalScope(AccessPermissionScope::class)
                ->with(['adminTypes'])
                ->find($uid);
        }, self::CACHE_EXPIRE);

        if ($toArray && $admin) {
            return $admin->toArray();
        }

        return $admin;
    }

    /**
     * 清除管理员缓存
     */
    public function clearCache(int|string|null $uid = null): void
    {
        $uid = $uid ?? $this->id();
        if ($uid) {
            $cacheKey = self::generateCacheKey($uid);
            $this->cache->delete($cacheKey);
            $this->cache->delete($cacheKey . '_permissions');
        }
    }

    /**
     * 刷新令牌
     */
    public function refresh(): array
    {
        if (!$this->getToken()) {
            Logger::debug("[platform] 当前用户无有效 Token，无法刷新", []);
            return [];
        }
        return (new JwtToken())->refresh()->toArray();
    }

    /**
     * 获取当前用户 ID
     */
    public function id(): int|string
    {
        $token = $this->getToken();
        if (!$token) {
            return 0;
        }
        $aid = (new JwtToken())->id();
        return $aid ?? 0;
    }

    /**
     * 判断是否为超级管理员（需查库）
     */
    public function isSuperAdmin(): bool
    {
        $admin = $this->admin();
        return $admin && $admin->isSuperAdmin();
    }

    /**
     * 从 JWT payload 中判断是否为平台级超管（platform/root 类型）
     *
     * 与 isSuperAdmin() 的区别：
     * - isSuperAdmin() 需要先查询数据库 Admin 记录
     * - isPlatformSuper() 仅从 JWT payload 中读取 types/admin_types，不查库
     */
    public function isPlatformSuper(): bool
    {
        $ext = $this->getPayload();
        $adminTypes = $ext['admin_types'] ?? $ext['types'] ?? [];
        return $this->hasAdminType($adminTypes, 'platform')
            && $this->hasAdminType($adminTypes, 'root');
    }

    /**
     * 检查管理员类型数组中是否包含指定类型编码
     * 支持两种格式：
     * - 对象格式: [{"id":1,"code":"admin"},{"id":2,"code":"platform"}]
     * - 字符串格式: ["admin", "platform"]
     */
    private function hasAdminType(array $adminTypes, string $code): bool
    {
        foreach ($adminTypes as $type) {
            if (is_array($type) && !empty($type['code']) && $type['code'] === $code) {
                return true;
            }
            if (is_string($type) && $type === $code) {
                return true;
            }
        }
        return false;
    }

    /**
     * 从请求中提取 Bearer Token
     */
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
     */
    public function generateToken(array $userInfo, string $type = 'platform'): array
    {
        $jwt = new JwtToken();
        $tokenObj = $jwt->generate((string)$this->id(), $type, $userInfo);
        return [
            'access_token'  => $tokenObj->accessToken,
            'refresh_token' => $tokenObj->refreshToken,
            'expires_in'    => $tokenObj->expiresIn,
            'expires_time'  => time() + $tokenObj->expiresIn,
        ];
    }

    /**
     * 登出（将令牌加入黑名单）
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
     */
    public function getPayload(): array
    {
        try {
            $jwt = new JwtToken();
            $payload = $jwt->getPayloadFromRequest();
            return $payload['extra'] ?? $payload;
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * 获取用户所有菜单权限码
     */
    public function getPermissions(): array
    {
        $admin = $this->admin();
        if (!$admin) {
            return [];
        }

        if ($admin->isSuperAdmin()) {
            return ['*'];
        }

        $uid = $this->id();
        $cacheKey = self::generateCacheKey($uid) . '_permissions';

        return $this->cache->remember($cacheKey, function () use ($admin) {
            $roles = $admin->roles()->with(['menus' => function ($query) {
                $query->where('enabled', 1)
                    ->whereNotNull('code')
                    ->where('code', '<>', '');
            }])->get();

            return $roles->pluck('menus')->flatten()->pluck('code')->unique()->values()->toArray();
        }, self::CACHE_EXPIRE);
    }

    /**
     * 检查用户是否有权限
     *
     * @param string|array $codes     权限码，可以是字符串或数组
     * @param string       $operation 操作类型，支持 'and' 或 'or'
     */
    public function hasPermission(string|array $codes, string $operation = 'and'): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        $codes = is_array($codes) ? $codes : [$codes];
        $userPermissions = $this->getPermissions();

        if (in_array('*', $userPermissions)) {
            return true;
        }

        $operation = strtolower($operation);

        if ($operation === Permission::OPERATION_AND) {
            foreach ($codes as $code) {
                if (!in_array($code, $userPermissions)) {
                    return false;
                }
            }
            return true;
        } elseif ($operation === Permission::OPERATION_OR) {
            foreach ($codes as $code) {
                if (in_array($code, $userPermissions)) {
                    return true;
                }
            }
            return false;
        }

        throw new \InvalidArgumentException("不支持的操作类型: {$operation}");
    }

    /**
     * 检查用户是否有权限，没有权限时抛出异常
     *
     * @throws ForbiddenHttpException
     */
    public function checkPermission(string|array $codes, string $operation = 'and'): void
    {
        if (!$this->hasPermission($codes, $operation)) {
            $codes = is_array($codes) ? $codes : [$codes];
            $codesStr = implode($operation === 'and' ? ',' : '或', $codes);
            throw new ForbiddenHttpException("缺少权限: {$codesStr}");
        }
    }
}
