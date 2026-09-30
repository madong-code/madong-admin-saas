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

namespace app\service\admin\system\admin;

use app\adminapi\event\system\LoginLogEvent;
use app\dao\system\admin\AdminDao;
use app\enum\system\PolicyPrefix;
use app\model\system\admin\Admin;
use app\model\tenant\Tenant;
use app\adminapi\CurrentUser;
use app\scope\global\TenantScope;
use app\service\admin\ops\logs\LoginLogService;
use core\foundation\base\BaseService;
use core\infrastructure\cache\CacheService;
use core\foundation\exception\handler\AdminException;
use core\security\jwt\JwtToken;
use core\business\tenant\TenantConnectionManager;
use core\business\tenant\context\TenantContext;
use core\foundation\tool\RSAService;
use support\Container;
use Webman\Event\Event;

/**
 * @method getAdminInfo(string $username)
 * @method getAdminById($uid, $withoutScopes = null)
 * @method getList(mixed $where, mixed $field, mixed $page, mixed $limit, mixed $order, array $array, false $false)
 * @method getUsersListByRoleId(mixed $where, mixed $field, mixed $page, mixed $limit)
 * @method getAdminByName(string $username, array|null $withoutScopes)
 */
class AdminService extends BaseService
{

    public function __construct(AdminDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * save
     *
     * @param array $data
     *
     * @return Admin|null
     * @throws \core\exception\handler\AdminException
     */
    public function save(array $data): Admin|null
    {
        try {
            return $this->transaction(function () use ($data) {
                $data['password'] = password_hash($data['password'], PASSWORD_DEFAULT);
                $roles            = $data['role_id_list'] ?? [];
                $posts            = $data['post_id_list'] ?? [];
                $depts            = array_filter(explode(',', $data['dept_id_list'] ?? ''));
                $mainDeptId       = $data['main_dept_id'] ?? null;
                $mainPosId        = $data['main_post_id'] ?? null;
                unset($data['role_id_list'], $data['post_id_list'], $data['dept_id_list'], $data['main_dept_id'], $data['main_post_id']);
                $model = $this->dao->save($data);

                $this->updateModel($model, $data, $depts, $posts, $roles);
                $this->syncRoles($model, $roles);
                $this->syncMainInfo($model, $mainDeptId, $mainPosId);
                return $model;
            });
        } catch (\Throwable $e) {
            throw new AdminException($e->getMessage());
        }
    }

    /**
     * 编辑
     *
     * @param int|string $id
     * @param array      $data
     *
     * @return \app\model\system\Admin|null
     * @throws \core\exception\handler\AdminException
     */
    public function update(int|string $id, array $data): ?Admin
    {
        try {
            return $this->transaction(function () use ($id, $data) {
                $this->updatePasswordIfNeeded($data);
                $roles = $data['role_id_list'] ?? [];
                $posts = $data['post_id_list'] ?? [];
                $depts = $data['dept_id_list'] ?? [];
                $mainDeptId = $data['main_dept_id'] ?? null;
                $mainPosId  = $data['main_post_id'] ?? null;
                unset($data['role_id_list'], $data['post_id_list'], $data['dept_id_list'], $data['main_dept_id'], $data['main_post_id']);
                $model = $this->dao->getModel()
                    ->findOrFail($id);
                $this->updateModel($model, $data, $depts, $posts, $roles);
                $this->syncRoles($model, $roles);
                $this->syncMainInfo($model, $mainDeptId, $mainPosId);
                return $model;
            });
        } catch (\Throwable $e) {
            throw new AdminException($e->getMessage());
        }
    }

    /**
     * 密码处理
     *
     * @param array $data
     */
    private function updatePasswordIfNeeded(array &$data): void
    {
        if (isset($data['password'])) {
            $data['password'] = password_hash($data['password'], PASSWORD_DEFAULT);
        }
    }

    /**
     * 更新-部门职位关系
     *
     * @param \app\model\system\Admin $model
     * @param array                   $data
     * @param array                   $depts
     * @param array                   $posts
     * @param array                   $roles
     */
    private function updateModel(Admin $model, array $data, array $depts, array $posts, array $roles): void
    {
        $model->fill($data);
        $model->save();
        $model->depts()->sync($depts);
        $model->posts()->sync($posts);
        $model->roles()->sync($roles);
    }

    /**
     * 同步-角色
     *
     * @param \app\model\system\Admin $model
     * @param array                   $roles
     */
    private function syncRoles(Admin $model, array $roles): void
    {
        $model->roles()->sync($roles);
    }

    private function syncMainInfo(Admin $model, ?string $mainDeptId, ?string $mainPosId): void
    {
        $mainInfo = \app\model\system\admin\AdminMain::updateOrCreate(
            ['admin_id' => (string)$model->id],
            [
                'main_dept_id' => $mainDeptId,
                'main_post_id'  => $mainPosId,
            ]
        );
    }

    /**
     * destroy
     *
     * @param $id
     * @param $force
     *
     * @return mixed
     * @throws \Exception
     */
    public function destroy($id, $force): mixed
    {
        $ret = $this->dao->count([['id', 'in', $id], ['is_super', '=', 1]]);
        if ($ret > 0) {
            throw new AdminException('系统内置用户，不允许删除');
        }
        return $this->dao->destroy($id);
    }

    /**
     * 用户-冻结
     *
     * @param array|string $id
     */
    public function locked(array|string $id): void
    {
        try {
            if (is_string($id)) {
                $id = array_map('trim', explode(',', $id));
            }
            $ret = $this->dao->count([['id', 'in', $id], ['is_super', '=', 1]]);
            if ($ret > 0) {
                throw new AdminException('系统内置用户，不允许冻结');
            }
            $this->dao->batchUpdate($id, ['is_locked' => 1]);
        } catch (\Throwable $e) {
            throw new AdminException($e->getMessage());
        }
    }

    /**
     * 用户-解除冻结
     *
     * @param array|string $id
     */
    public function unLocked(array|string $id): void
    {
        try {
            if (is_string($id)) {
                $id = array_map('trim', explode(',', $id));
            }
            $this->dao->batchUpdate($id, ['is_locked' => 0]);
        } catch (\Throwable $e) {
            throw new AdminException($e->getMessage());
        }
    }

    /**
     * 用户登录（双层认证分流）
     *
     * 认证路径：
     *   1. 先查主库 sys_admin → 平台管理员（super admin）
     *   2. 主库未命中 + 有 tenant_id → 定位租户库 sys_admin → 租户管理员
     *
     * @param string $username
     * @param string $password
     * @param string $type
     * @param string $grantType
     * @param array  $params
     *
     * @return array
     * @throws \core\exception\handler\AdminException
     */
    public function login(string $username, string $password = '', string $type = 'admin', string $grantType = 'default', array $params = []): array
    {
        $tenantId   = $params['tenant_id'] ?? null;
        $tenantCode = $params['tenant_code'] ?? null;
        $tenant     = null;

        // 是否启用多租户
        $multiTenantEnabled = config('tenant.enabled', false) || filter_var(env('APP_TENANT_ENABLED', false), FILTER_VALIDATE_BOOLEAN);

        if ($multiTenantEnabled) {
            // ========== 多租户登录流程 ==========

            // 1. 租户标识必传：优先租户代码（tenant_code），其次租户ID（tenant_id）
            if (empty($tenantCode) && empty($tenantId)) {
                throw new AdminException('多租户模式下请选择租户或输入租户代码后登录');
            }

            // 2. 获取租户详情（包含 database_mode / database_name / db_setting 等）
            if (!empty($tenantCode)) {
                if (!Tenant::validateCode((string)$tenantCode)) {
                    throw new AdminException('租户代码格式不正确');
                }
                $tenant = Tenant::findByCode((string)$tenantCode);
                if (!$tenant) {
                    throw new AdminException('租户不存在（代码: ' . $tenantCode . '）');
                }
            } else {
                $tenant = Tenant::withoutGlobalScopes()->find($tenantId);
                if (!$tenant) {
                    throw new AdminException('租户不存在（ID: ' . $tenantId . '）');
                }
            }

            if (!$tenant->isActive()) {
                throw new AdminException('租户已停用');
            }

            // 统一归一为租户ID，供后续分支判断与连接切换使用
            $tenantId = (string)$tenant->id;

            $isDatabaseMode = $tenant->database_mode === Tenant::MODE_DATABASE;

            // 3. 先查主库（默认连接），判断是否为 root/platform 类型账号
            //    注意：Webman 长进程中 TenantContext 可能有前次请求残留，
            //    Admin 模型 getConnectionName() 有可能返回租户库连接，
            //    因此必须通过 Admin::on(config('database.default')) 强制使用主库连接查询
            $defaultConnection = config('database.default', 'mysql');
            $adminInfo = Admin::on($defaultConnection)
                ->where('user_name', $username)
                ->withoutGlobalScopes([TenantScope::class])
                ->first();
            $isPlatformAdmin = $adminInfo && (
                in_array('platform', $adminInfo->getTypes()) ||
                in_array('root', $adminInfo->getTypes())
            );

            // 4. 分支A：平台超管（root / platform）→ admin 记录在主库
            if ($isPlatformAdmin) {
                $this->validateAdminStatus($adminInfo);
                $this->validatePassword($adminInfo, $password, $grantType);

                [$userInfo, $token] = $this->generateTokenData($adminInfo, $type, $tenant, 'main');
                $this->emitLoginSuccessEvent(array_merge($userInfo, $token), $tenant?->id ?? null);
                return $token ?? [];
            }

            // 5. 分支B：普通租户用户（按隔离模式查找）
            if ($isDatabaseMode) {
                // 分库模式 → 切到租户独立库查询，admin 记录在租户库
                TenantConnectionManager::setCurrentConnection($tenantId, false);
                $adminInfo = Admin::where('user_name', $username)->first();
                $adminDbSource = 'tenant';
            } else {
                // 字段隔离模式 → 校验主库中 tenant_id 字段匹配，admin 记录在主库
                $tenantColumn = config('tenant.field_isolation.tenant_column', 'tenant_id');
                if ($adminInfo && (string)$adminInfo->{$tenantColumn} !== (string)$tenantId) {
                    // 多个租户可能有同名的 admin，重新按 tenant_id 精确查询
                    $adminInfo = Admin::on($defaultConnection)
                        ->where('user_name', $username)
                        ->where($tenantColumn, $tenantId)
                        ->withoutGlobalScopes([TenantScope::class])
                        ->first();
                }
                $adminDbSource = 'main';
            }

            // 6. 验证并登录
            // 多租户下账号查找范围已限定到指定租户，账号不存在与密码错误需区分提示，
            // 否则用户在「租户正确但没有该账号」时会误以为密码有问题
            if (!$adminInfo) {
                throw new AdminException('账号不存在');
            }
            $this->validateAdminStatus($adminInfo);
            $this->validatePassword($adminInfo, $password, $grantType);

            [$userInfo, $token] = $this->generateTokenData($adminInfo, $type, $tenant, $adminDbSource);
            $this->emitLoginSuccessEvent(array_merge($userInfo, $token), $tenant?->id ?? null);
            return $token ?? [];
        }

        // ========== 非多租户模式 ==========
        // 同样使用默认连接查询，避免 TenantContext 残留影响连接选择
        $defaultConnection = config('database.default', 'mysql');
        $adminInfo = Admin::on($defaultConnection)
            ->where('user_name', $username)
            ->first();

        $this->validateAdminStatus($adminInfo);
        $this->validatePassword($adminInfo, $password, $grantType);

        [$userInfo, $token] = $this->generateTokenData($adminInfo, $type, $tenant);
        $this->emitLoginSuccessEvent(array_merge($userInfo, $token), $tenant?->id ?? null);
        return $token ?? [];
    }

    /**
     * 根据用户名和租户解析管理员账号（非多租户模式）
     *
     * @param string       $username
     * @param string|null  $tenantId
     * @param Tenant|null  $tenant
     * @return Admin|null
     */
    private function resolveAdminByTenant(string $username, ?string $tenantId, ?Tenant $tenant): ?Admin
    {
        // 非多租户模式：仅按用户名查询
        return $this->dao->getAdminByName($username, [TenantScope::class]);
    }

    /**
     * 第三方应用登录
     *
     * @param mixed  $acctId
     * @param mixed  $appId
     * @param mixed  $appSecret
     * @param mixed  $userName
     * @param string $type
     *
     * @return array
     * @throws \core\exception\handler\AdminException
     */
    public function thirdPartyLogin(mixed $acctId, mixed $appId, mixed $appSecret, mixed $userName, string $type = 'admin'): array
    {
        $adminInfo = $this->getAdminByName($userName);
        $this->validateAdminStatus($adminInfo);
        $this->validateThirdPartyApp($acctId, $appId, $appSecret);
        [$userInfo, $token] = $this->generateTokenData($adminInfo, $type, null, 'main');
        $this->emitLoginSuccessEvent(array_merge($userInfo, $token), $tenant?->id ?? null);
        return $token ?? [];
    }

    private function validateThirdPartyApp(mixed $acctId, mixed $appId, mixed $appSecret)
    {

        if ($acctId !== config('app.acct_id')) {
            throw new AdminException('第三方应用不存在或已删除');
        }
        if ($appId !== config('app.app_id')) {
            throw new AdminException('第三方应用ID错误');
        }
        if ($appSecret !== config('app.app_secret')) {
            throw new AdminException('第三方应用ID或密钥错误');
        }
    }

    /**
     * 验证管理员状态
     *
     * @param \app\model\system\Admin|null $adminInfo
     *
     * @throws \core\exception\handler\AdminException
     */
    private function validateAdminStatus(?Admin $adminInfo): void
    {
        if (!$adminInfo) {
            throw new AdminException('账号或密码错误，请重新输入!');
        }
        if ($adminInfo->enabled === 0) {
            throw new AdminException('您已被禁止登录!');
        }
        if ($adminInfo->is_locked === 1) {
            throw new AdminException('您的账号已被锁定，禁止登录!');
        }
    }

    /**
     * 验证密码
     *
     * @param \app\model\system\Admin $adminInfo
     * @param string                  $password
     * @param string                  $grantType
     *
     * @throws \core\exception\handler\AdminException
     */
    private function  validatePassword(Admin $adminInfo, string $password, string $grantType): void
    {
        if (!in_array($grantType, ['sms', 'refresh_token']) && !password_verify($password, $adminInfo->password)) {
            $msg = '账号或密码错误，请重新输入!';
            $this->emitLoginFailedEvent($adminInfo->toArray(), $msg);
            throw new AdminException($msg);
        }
    }

    /**
     * token生成
     *
     * @param \app\model\system\Admin $adminInfo
     * @param string                  $type
     *
     * @return array
     */
    private function generateTokenData(Admin $adminInfo, string $type, ?\app\model\tenant\Tenant $tenant = null, string $adminDbSource = 'main'): array
    {
        $loginIp = request()->getRealIp();
        $userAgent = request()->header('user-agent', '');
        $browser = $this->getBrowser($userAgent);
        $os = $this->getOs($userAgent);
        $ipLocation = $this->getIpLocation($loginIp);
        
        $adminInfo->login_time = time();
        $adminInfo->login_ip   = $loginIp;
        $adminInfo->save();
        $userInfo = $adminInfo->makeHidden([
            'password',
            'backend_setting',
            'created_by',
            'updated_by',
            'created_at',
            'deleted_at',
            'remark',
            'created_date',
            'updated_date',
        ])->toArray();
        
        // 追加扩展数据到 userInfo（用于 JWT extra）
        $userInfo['ip'] = $loginIp;
        $userInfo['ip_location'] = $ipLocation;
        $userInfo['browser'] = $browser;
        $userInfo['os'] = $os;

        // 追加管理员类型编码（用于区分平台超管和租户级超管）
        $adminTypes = $adminInfo->getTypes();
        // 兼容旧系统：is_super=1 且无 tenant_id（系统级），自动注入 platform 和 root 类型
        // 注意：仅在 admin_db_source='main'（主库记录）时才注入，避免将租户库中的
        // 管理员误标记为平台超管，导致后续请求路由到错误数据库
        if ($adminDbSource === 'main' && $adminInfo->isSuperAdmin() && empty($adminInfo->tenant_id)) {
            if (!in_array('platform', $adminTypes)) {
                $adminTypes[] = 'platform';
            }
            if (!in_array('root', $adminTypes)) {
                $adminTypes[] = 'root';
            }
        }
        $userInfo['admin_types'] = $adminTypes;

        // 记录 Admin 记录归属的数据库来源（main/tenant），用于后续请求精确路由
        $userInfo['admin_db_source'] = $adminDbSource;

        // 写入 is_super 到 JWT extra（使用 int 类型，与数据库一致）
        $userInfo['is_super'] = (int)$adminInfo->is_super;

        // 多租户：注入当前登录选择的租户信息
        if ($tenant) {
            $userInfo['current_tenant'] = [
                'id'            => $tenant->id,
                'name'          => $tenant->name,
                'code'          => $tenant->code,
                'database_mode' => $tenant->database_mode,
                'database_name' => $tenant->database_name,
                'domain'        => $tenant->domain,
            ];
        }
        
        // 使用新的 JwtToken 生成 token
        $jwt = new JwtToken();
        $tokenObj = $jwt->generate((string)$adminInfo->id, $type, $userInfo);
        
        $token = [
            'access_token' => $tokenObj->accessToken,
            'refresh_token' => $tokenObj->refreshToken,
            'expires_in' => $tokenObj->expiresIn,
            'client_id' => $this->generateUniqueId($loginIp),
            'expires_time' => time() + $tokenObj->expiresIn,
            // 多租户：返回租户信息便于前端展示
            'tenant' => $tenant ? [
                'id'            => $tenant->id,
                'name'          => $tenant->name,
                'code'          => $tenant->code,
                'database_mode' => $tenant->database_mode,
            ] : null,
        ];
        
        return [$userInfo, $token];
    }

    /**
     * 切换租户（已登录状态下更换当前租户，不更新登录记录）
     *
     * @param int|string $adminId  管理员ID
     * @param int|string $tenantId 目标租户ID
     *
     * @return array 新的 token 数据（与 login 返回结构一致）
     * @throws AdminException
     */
    public function switchTenant(int|string $adminId, int|string $tenantId): array
    {
        // 通过 CurrentUser::getAdminFromMainDb() 从主库获取 admin 信息
        // 该方法的 Admin::on(config('database.default')) 保证始终查询主库连接
        // 不依赖当前 TenantContext 隔离模式，避免全局状态污染
        /** @var CurrentUser $currentUser */
        $currentUser = Container::make(CurrentUser::class);
        $admin = $currentUser->getAdminFromMainDb();

        if (!$admin) {
            throw new AdminException('用户不存在');
        }
        $this->validateAdminStatus($admin);

        // 验证租户有效性
        $tenant = Tenant::withoutGlobalScopes()->find($tenantId);
        if (!$tenant || !$tenant->isActive()) {
            throw new AdminException($tenant ? '租户已停用' : '租户不存在（ID: ' . $tenantId . '）');
        }

        // 平台级管理员（is_super、platform、root 类型）允许访问任何租户
        $isPlatformAdmin = $admin->isSuperAdmin() || $admin->hasType('platform') || $admin->hasType('root');

        if (!$isPlatformAdmin) {
            // 非平台管理员：验证 admin.tenant_id 是否匹配目标租户
            if ((string)$admin->tenant_id !== (string)$tenantId) {
                throw new AdminException('无权限访问该租户');
            }
        }

        // 先清理当前 Token（旧租户的会话），避免 Redis 中残留无效 Token
        $jwt = new JwtToken();
        $jwt->logout();

        // 设置租户上下文（对所有模式生效，field/database 统一）
        TenantContext::setTenant($tenantId);
        if ($tenant->database_mode === Tenant::MODE_DATABASE) {
            TenantConnectionManager::setCurrentConnection($tenantId);
            TenantContext::setIsolationMode('database');
        } else {
            TenantContext::setIsolationMode('field');
        }

        // 构建用户信息（与 generateTokenData 保持字段一致）
        $loginIp   = request()->getRealIp();
        $userAgent = request()->header('user-agent', '');

        $userInfo = $admin->makeHidden([
            'password',
            'backend_setting',
            'created_by',
            'updated_by',
            'created_at',
            'deleted_at',
            'remark',
            'created_date',
            'updated_date',
        ])->toArray();

        // 追加扩展数据（与 generateTokenData 一致，保证会话渲染完整）
        $userInfo['ip']          = $loginIp;
        $userInfo['ip_location'] = $this->getIpLocation($loginIp);
        $userInfo['browser']     = $this->getBrowser($userAgent);
        $userInfo['os']          = $this->getOs($userAgent);
        $userInfo['is_super']    = (int)$admin->is_super;

        // 追加管理员类型编码（用于区分平台超管和租户级超管）
        $adminTypes = $admin->getTypes();
        // 兼容旧系统：is_super=1 且无 tenant_id（系统级），自动注入 platform 和 root 类型
        if ($admin->isSuperAdmin() && empty($admin->tenant_id)) {
            if (!in_array('platform', $adminTypes)) {
                $adminTypes[] = 'platform';
            }
            if (!in_array('root', $adminTypes)) {
                $adminTypes[] = 'root';
            }
        }
        $userInfo['admin_types'] = $adminTypes;

        // 切换租户时 admin 记录来自主库
        $userInfo['admin_db_source'] = 'main';

        $userInfo['current_tenant'] = [
            'id'            => $tenant->id,
            'name'          => $tenant->name,
            'code'          => $tenant->code,
            'database_mode' => $tenant->database_mode,
            'database_name' => $tenant->database_name,
            'domain'        => $tenant->domain,
        ];

        // 生成新 token
        $tokenObj = $jwt->generate((string)$admin->id, 'admin', $userInfo);

        return [
            'access_token'  => $tokenObj->accessToken,
            'refresh_token' => $tokenObj->refreshToken,
            'expires_in'    => $tokenObj->expiresIn,
            'client_id'     => $this->generateUniqueId(request()?->getRealIp()),
            'expires_time'  => time() + $tokenObj->expiresIn,
            'tenant'        => [
                'id'            => $tenant->id,
                'name'          => $tenant->name,
                'code'          => $tenant->code,
                'database_mode' => $tenant->database_mode,
            ],
        ];
    }

    /**
     * 登录成功-事件发起
     *
     * @param array    $tokenData
     * @param int|null $tenantId
     */
    private function emitLoginSuccessEvent(array $tokenData, ?int $tenantId = null): void
    {
        $loginIp = request()->getRealIp();
        $event = new LoginLogEvent(
            '登录成功',
            request()->app,
            $loginIp,
            $this->getIpLocation($loginIp),
            $this->getBrowser(request()->header('user-agent', '')),
            $this->getOs(request()->header('user-agent', '')),
            0,
            '登录成功',
            $tokenData['user_name'],
            $tokenData['id'],
            time(),
            $tokenData['access_token'],
            $tokenData['expires_time']
        );
        $event->dispatch();
    }

    /**
     * 登录失败-事件发起
     *
     * @param array  $adminInfo
     * @param string $message
     */
    private function emitLoginFailedEvent(array $adminInfo, string $message): void
    {
        $loginIp = request()->getRealIp();
        $event = new LoginLogEvent(
            '登录失败',
            request()->app,
            $loginIp,
            $this->getIpLocation($loginIp),
            $this->getBrowser(request()->header('user-agent', '')),
            $this->getOs(request()->header('user-agent', '')),
            -1,
            $message,
            $adminInfo['user_name'],
            $adminInfo['id'],
            time(),
            '',
            time()
        );
        $event->dispatch();
    }
    
    /**
     * 获取浏览器信息
     *
     * @param string $userAgent
     * @return string
     */
    private function getBrowser(string $userAgent): string
    {
        $br = 'Unknown';
        if (preg_match('/MSIE/i', $userAgent)) {
            $br = 'MSIE';
        } elseif (preg_match('/Firefox/i', $userAgent)) {
            $br = 'Firefox';
        } elseif (preg_match('/Chrome/i', $userAgent)) {
            $br = 'Chrome';
        } elseif (preg_match('/Safari/i', $userAgent)) {
            $br = 'Safari';
        } elseif (preg_match('/Opera/i', $userAgent)) {
            $br = 'Opera';
        } else {
            $br = 'Other';
        }
        return $br;
    }
    
    /**
     * 获取操作系统信息
     *
     * @param string $userAgent
     * @return string
     */
    private function getOs(string $userAgent): string
    {
        $os = 'Unknown';
        if (preg_match('/win/i', $userAgent)) {
            $os = 'Windows';
        } elseif (preg_match('/mac/i', $userAgent)) {
            $os = 'Mac';
        } elseif (preg_match('/linux/i', $userAgent)) {
            $os = 'Linux';
        } else {
            $os = 'Other';
        }
        return $os;
    }
    
    /**
     * 获取IP归属地
     *
     * @param string $ip
     * @return string
     */
    private function getIpLocation(string $ip): string
    {
        // 本地IP或无效IP直接返回
        if (empty($ip) || in_array($ip, ['127.0.0.1', '::1', 'localhost', '0.0.0.0'])) {
            return '本地';
        }

        // 使用新浪API获取IP归属地
        try {
            // 创建Guzzle HTTP客户端
            $client = new \GuzzleHttp\Client([
                'timeout' => 2,
                'connect_timeout' => 1,
                'verify' => false
            ]);

            $url = "http://int.dpool.sina.com.cn/iplookup/iplookup.php?format=json&ip=" . $ip;
            $response = $client->get($url);

            $content = $response->getBody()->getContents();

            $data = json_decode($content, true);
            if (isset($data['ret']) && $data['ret'] === 1 && !empty($data['city'])) {
                $location = $data['city'];
                if (!empty($data['province']) && strpos($data['province'], $data['city']) === false) {
                    $location = $data['province'] . ' ' . $location;
                }
                return $location;
            }
        } catch (\Throwable $e) {
            // 忽略异常
        }

        return '未知';
    }

    /**
     * 更新个人中心-用户信息
     */
    public function updateProfile(string|int $id, array $data, ?string $connectionName = null): void
    {
        $this->transaction(function () use ($id, $data, $connectionName) {
            $this->resolveProfileAdmin($id, $connectionName)->fill($data)->save();
            $this->clearProfileCache($id);
        });
    }

    /**
     * 更新个人中心-密码
     */
    public function updateProfilePassword(string|int $id, string $oldPassword, string $newPassword, ?string $connectionName = null): void
    {
        $this->transaction(function () use ($id, $oldPassword, $newPassword, $connectionName) {
            $admin = $this->resolveProfileAdmin($id, $connectionName);
            if (!password_verify($oldPassword, $admin->password)) {
                throw new AdminException('旧密码错误，请重新输入!');
            }
            $admin->fill(['password' => password_hash($newPassword, PASSWORD_DEFAULT)])->save();
            $this->clearProfileCache($id);
        });
    }

    /**
     * 更新个人中心-头像
     */
    public function updateProfileAvatar(string|int $id, string $avatarUrl, ?string $connectionName = null): void
    {
        $this->transaction(function () use ($id, $avatarUrl, $connectionName) {
            $this->resolveProfileAdmin($id, $connectionName)->fill(['avatar' => $this->getPathFromUrl($avatarUrl)])->save();
            $this->clearProfileCache($id);
        });
    }

    /**
     * 获取个人中心操作的干净模型
     * - $connectionName 非空：平台管理员跨租户时指定主库连接
     * - $connectionName 为空：普通用户走 DAO 默认连接
     * - findOrFail 返回全新实例，无 AdminDao 注入的虚拟属性
     */
    private function resolveProfileAdmin(string|int $id, ?string $connectionName): Admin
    {
        if ($connectionName) {
            return Admin::on($connectionName)
                ->withoutGlobalScope(TenantScope::class)
                ->findOrFail($id);
        }
        return $this->dao->getModel()->findOrFail($id);
    }

    /**
     * 清除个人中心相关缓存
     *
     * @param string|int $id
     */
    private function clearProfileCache(string|int $id): void
    {
        /** @var CacheService $cache */
        $cache = Container::make(CacheService::class);
        $baseKey = CurrentUser::generateCacheKey((string)$id);
        $cache->delete($baseKey);
        $tenantId = TenantContext::getTenantId();
        if ($tenantId) {
            $cache->delete($baseKey . '_db_' . $tenantId);
        }
        $cache->delete($baseKey . '_maindb');
    }

    /**
     * 更新个人偏好设置
     *
     * @throws \Throwable
     */
    public function updateUserPreferences(string|int $id, array $data = []): void
    {
        $this->transaction(function () use ($id, $data) {
            unset($data['id']);
            return $this->dao->update(['id' => $id], ['backend_setting' => $data]);
        });
    }

    /**
     * 强制下线
     *
     * @param $token
     *
     * @throws \Throwable
     */
    public function kickoutByTokenValueUser($token): void
    {
        $this->transaction(function () use ($token) {
            /** @var LoginLogService $systemLoginLogService */
            $systemLoginLogService = Container::make(LoginLogService::class);
            // 使用 firstOrFail 避免空值检查
            $loginLog = $systemLoginLogService->getModel()
                ->where('key', $token)
                ->firstOrFail();

            // 批量更新字段
            $loginLog->update([
                'expires_at' => time(), // 使用 Carbon 时间
                'remark'     => '强制下线',
                'updated_at' => time(), // 确保更新时间戳
            ]);

            // 添加Token 致黑名单
            $result = JwtToken::addToBlacklist($token, true);
            if (!$result) {
                throw new AdminException('操作失败');
            }
        });
    }

    /**
     * 删除关联源-并清理残留关联数据
     *
     * @param array $ids
     *
     * @return array
     * @throws \core\exception\handler\AdminException
     */
    public function batchDelete(array $ids): array
    {
        try {
            return $this->transaction(function () use ($ids) {
                // 1. 验证：禁止删除超级管理员
                $superUserCount = $this->dao->getModel()->whereIn('id', $ids)
                    ->where('is_super', 1)
                    ->count();
                if ($superUserCount > 0) {
                    throw new AdminException('系统内置用户不允许删除');
                }

                // 2. 关联同步：通过模型关联清理角色中间表数据

                $admins = $this->dao->getModel()->whereIn('id', $ids)->get();
                foreach ($admins as $admin) {
                    // 解除角色关联
                    $admin->roles()->detach();
                    $admin->depts()->detach();
                }

                // 4. 执行管理员数据删除
                $deleteCount = $this->dao->destroy($ids);
                if ($deleteCount <= 0) {
                    throw new AdminException('删除失败，未找到有效用户');
                }
                return ['id' => $ids];
            });
        } catch (\Throwable $e) {
            throw new AdminException($e->getMessage());
        }
    }

    /**
     * 生成唯一UUID
     *
     * @param string|null $currentIp
     *
     * @return string
     */
    private function generateUniqueId(string|null $currentIp = null): string
    {
        if (empty($currentIp)) {
            $currentIp = request()->getRemoteIp();
        }
        return uniqid($currentIp . '-', true);
    }

    /**
     * 入参移除url
     *
     * @param string $url
     *
     * @return string
     */
    private function getPathFromUrl(string $url): string
    {
        // 统一用 parse_url 提取路径部分，兼容 http/https/协议相对(//host/path) 和纯路径格式
        $path = parse_url($url, PHP_URL_PATH);
        return $path ?: $url;
    }

    /**
     * 校验密钥
     *
     * @param $keyId
     * @param $encryptedPassword
     *
     * @return string
     * @throws AdminException
     */
    private function validateRsaKeys($keyId, $encryptedPassword): string
    {
        $cache      = Container::make(CacheService::class, []);
        $privateKey = $cache->get("rsa_private_key_$keyId");
        if (!$privateKey) {
            throw new AdminException('私钥不存在或已过期，请刷新页面重试');
        }
        return RSAService::decrypt($encryptedPassword, $privateKey);
    }
}


