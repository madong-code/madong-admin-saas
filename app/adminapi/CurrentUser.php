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

namespace app\adminapi;

use app\model\system\admin\Admin;
use app\model\system\menu\Menu;
use app\model\system\role\RoleMenu;
use app\scope\global\AccessPermissionScope;
use app\scope\global\TenantScope;
use app\service\admin\system\admin\AdminService;
use core\infrastructure\cache\CacheService;
use core\foundation\exception\handler\ForbiddenHttpException;
use core\security\jwt\JwtToken;
use core\infrastructure\logger\Logger;
use core\business\tenant\context\TenantContext;
use madong\swagger\attribute\Permission;
use support\Container;
use support\Log;

final  readonly class CurrentUser
{
    // 缓存键前缀
    public const CACHE_PREFIX = 'admin_user';

    // 缓存过期时间（秒）
    public const CACHE_EXPIRE = 3600;

    private AdminService $service;
    private CacheService $cache;

    public function __construct(AdminService $service, CacheService $cache)
    {
        $this->service = $service;
        $this->cache = $cache;
    }

    /**
     * 生成管理员缓存键
     * @param int|string $uid 管理员ID
     * @return string 缓存键
     */
    public static function generateCacheKey(int|string $uid): string
    {
        return self::CACHE_PREFIX . '_' . $uid;
    }

    public function admin(bool $toArray = false): null|Admin|array
    {
        if (!$this->getToken()) {
            return null;
        }

        $uid = $this->id();

        // database 隔离模式下
        if (TenantContext::getIsolationMode() === 'database') {
            $payload = $this->getPayload();
            $adminDbSource = $payload['admin_db_source'] ?? null;

            if ($adminDbSource === 'main') {
                // admin 记录在主库（平台超管）
                return $this->getAdminFromMainDb($toArray);
            }

            // admin_db_source = 'tenant'，或旧 token（无该字段）→ 查租户独立库
            // 使用连接名作为缓存后缀，避免与旧缓存 key 冲突
            $tenantId = TenantContext::getTenantId();
            $connectionName = 'tenant_' . $tenantId;
            $dbCacheKey = self::generateCacheKey($uid) . '_db_' . $tenantId;
            $admin = $this->cache->remember($dbCacheKey, function () use ($uid, $connectionName) {
                return Admin::on($connectionName)
                    ->withoutGlobalScope(TenantScope::class)
                    ->with(['depts', 'posts', 'roles', 'adminTypes'])
                    ->find($uid);
            }, self::CACHE_EXPIRE);

            if ($toArray && $admin) {
                $data = $admin->toArray();
                $payload = $this->getPayload();
                if (!empty($payload['current_tenant'])) {
                    $data['current_tenant'] = $payload['current_tenant'];
                }
                return $data;
            }
            return $admin;
        }

        // field / 其它模式：当前连接查库
        $cacheKey = self::generateCacheKey($uid);
        $admin = $this->cache->remember($cacheKey,  function () use ($uid) {
            return $this->service->get($uid, ['*'], [], '', [AccessPermissionScope::class, TenantScope::class]);
        }, self::CACHE_EXPIRE);

        if ($toArray && $admin) {
            $data = $admin->toArray();
            $payload = $this->getPayload();
            if (!empty($payload['current_tenant'])) {
                $data['current_tenant'] = $payload['current_tenant'];
            }
            return $data;
        }

        return $admin;
    }

    /**
     * 清除管理员缓存
     * @param int|string|null $uid 管理员ID，默认清除当前用户缓存
     */
    public function clearCache(int|string|null $uid = null): void
    {
        $uid = $uid ?? $this->id();
        if ($uid) {
            $cacheKey = self::generateCacheKey($uid);
            $this->cache->delete($cacheKey);
            // 清除主库查询独立缓存
            $this->cache->delete($cacheKey . '_maindb');
            // 同时清除权限缓存
            $this->cache->delete($cacheKey . '_permissions');
        }
    }

    public function refresh(): array
    {
        if (!$this->getToken()) {
            Logger::debug("当前用户无有效 Token，无法刷新", []);
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
        $aid = (new JwtToken())->id();
        if ($aid === null) {
            return 0;
        }
        return $aid ?? 0;
    }

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
     * - isPlatformSuper() 仅从 JWT payload 中读取 admin_types，不查库
     *
     * @return bool
     */
    public function isPlatformSuper(): bool
    {
        $ext = $this->getPayload();
        $adminTypes = $ext['admin_types'] ?? [];
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
     * 在主库（mysql 连接）上获取管理员信息
     *
     * 适用于 platform/root 超管从租户库连接切换到主库查询的场景。
     * 使用 Admin::on('mysql') 直接指定连接，不修改全局 TenantContext 状态。
     *
     * 兜底逻辑：如果 JWT payload 中的 ID 在主库中不存在（例如历史数据中
     * JWT 为雪花 ID 但主库 admin 为自增 ID=1），则按 user_name 回退查找。
     *
     * @param bool $toArray 是否返回数组
     * @return null|Admin|array
     */
    public function getAdminFromMainDb(bool $toArray = false): null|Admin|array
    {
        if (!$this->getToken()) {
            Log::debug('[DBG] getAdminFromMainDb no token');
            return null;
        }
        $uid = $this->id();
        // 用独立缓存键避免与 admin() 方法的缓存冲突
        $mainDbCacheKey = self::generateCacheKey($uid) . '_maindb';

        Log::debug('[DBG] getAdminFromMainDb uid=' . $uid . ' cacheKey=' . $mainDbCacheKey);

        $admin = $this->cache->remember($mainDbCacheKey, function () use ($uid) {
            $connectionName = config('database.default');
            Log::debug('[DBG] callback connection=' . $connectionName . ' uid=' . $uid);

            // 1. 先用 JWT payload 中的 ID 查找
            $admin = Admin::on($connectionName)
                ->withoutGlobalScope(TenantScope::class)
                ->with(['depts', 'posts', 'roles', 'adminTypes'])
                ->find($uid);

            Log::debug('[DBG] callback findById=' . ($admin ? 'found' : 'null'));
            if ($admin) {
                Log::debug('[DBG] callback findById attrs=' . json_encode($admin->getAttributes(), JSON_UNESCAPED_UNICODE));
            }

            // 2. 兜底：ID 不匹配时（如主库 admin ID=1 但 JWT 为雪花 ID），
            //    用 payload 中的 user_name 回退查找
            if (empty($admin)) {
                $payload = $this->getPayload();
                $userName = $payload['user_name'] ?? '';
                Log::debug('[DBG] callback fallback user_name=' . $userName);
                if ($userName) {
                    // 注意：不用 with() 避免 fallback 路径产生额外关系查询
                    $admin = Admin::on($connectionName)
                        ->withoutGlobalScope(TenantScope::class)
                        ->where('user_name', $userName)
                        ->first();
                    Log::debug('[DBG] callback fallback=' . ($admin ? 'found' : 'null'));
                    if ($admin) {
                        // 找到后手动加载关系
                        $admin->load(['depts', 'posts', 'roles', 'adminTypes']);
                        Log::debug('[DBG] callback fallback id=' . $admin->id . ' is_super=' . $admin->is_super);
                        Log::debug('[DBG] callback fallback attrs=' . json_encode($admin->getAttributes(), JSON_UNESCAPED_UNICODE));
                    }
                }
            }

            return $admin;
        }, self::CACHE_EXPIRE);

        if ($toArray && $admin) {
            // 直接使用 getAttributes() 代替 toArray() 避免 Eloquent 序列化问题
            $data = $admin->getAttributes();
            Log::debug('[DBG] getAttributes keys=' . implode(',', array_keys($data)));
            Log::debug('[DBG] getAttributes empty=' . (empty($data) ? 'YES' : 'NO'));

            // 手动补充 $appends 字段
            foreach ($admin->getAppends() as $appendKey) {
                $data[$appendKey] = $admin->$appendKey ?? null;
            }
            // 手动补充关系数据
            foreach ($admin->getRelations() as $relName => $relValue) {
                if ($relValue instanceof \Illuminate\Support\Collection) {
                    $data[$relName] = $relValue->toArray();
                } elseif ($relValue instanceof \Illuminate\Database\Eloquent\Model) {
                    $data[$relName] = $relValue->toArray();
                } else {
                    $data[$relName] = $relValue;
                }
            }
            // 从 JWT payload 中注入当前租户信息
            $payload = $this->getPayload();
            if (!empty($payload['current_tenant'])) {
                $data['current_tenant'] = $payload['current_tenant'];
            }
            return $data;
        }

        return $admin;
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
    public function generateToken(array $userInfo, string $type = 'admin'): array
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
     * 获取用户所有菜单权限码
     *
     * @return array 权限码数组
     */
    public function getPermissions(): array
    {
        $admin = $this->admin();
        if (!$admin) {
            return [];
        }

        if ($admin->isSuperAdmin()) {
            return ['*']; // 超级管理员拥有所有权限
        }

        // 直接查询数据库，不缓存权限数据
        // 通过角色→sys_role_menu 中间表获取菜单ID，绕过 Menu 模型的 TenantScope
        // （避免全局 TenantScope 的 WHERE tenant_id = X 过滤掉公共菜单）
        $roleIds = $admin->roles()->pluck('sys_role.id')->toArray();
        if (empty($roleIds)) {
            return [];
        }
        
        $connectionName = $admin->getConnectionName();
        $menuIds = RoleMenu::on($connectionName)
            ->whereIn('role_id', $roleIds)
            ->pluck('menu_id')
            ->unique()
            ->toArray();
        
        if (empty($menuIds)) {
            return [];
        }
        
        // 查询有 code 的菜单（按钮/接口类型），不带 TenantScope 以包含公共菜单
        return Menu::on($connectionName)
            ->withoutGlobalScope(TenantScope::class)
            ->whereIn('id', $menuIds)
            ->where('enabled', 1)
            ->whereNotNull('code')
            ->where('code', '<>', '')
            ->pluck('code')
            ->unique()
            ->values()
            ->toArray();
    }

    /**
     * 检查用户是否有权限
     *
     * @param string|array $codes 权限码，可以是字符串或数组
     * @param string $operation 操作类型，支持 'and' 或 'or'
     * @return bool 是否有权限
     */
    public function hasPermission(string|array $codes, string $operation = 'and'): bool
    {
        // 如果是超级管理员，直接返回true
        if ($this->isSuperAdmin()) {
            return true;
        }

        $codes = is_array($codes) ? $codes : [$codes];
        $userPermissions = $this->getPermissions();

        // 如果有通配符权限，直接返回true
        if (in_array('*', $userPermissions)) {
            return true;
        }

        $operation = strtolower($operation);
        
        if ($operation === Permission::OPERATION_AND) {
            // AND模式：所有权限码都必须有
            foreach ($codes as $code) {
                if (!in_array($code, $userPermissions)) {
                    return false;
                }
            }
            return true;
        } else if ($operation === Permission::OPERATION_OR) {
            // OR模式：至少有一个权限码
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
     * @param string|array $codes 权限码，可以是字符串或数组
     * @param string $operation 操作类型，支持 'and' 或 'or'
     * @throws ForbiddenHttpException 没有权限时抛出异常
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