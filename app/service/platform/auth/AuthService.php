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
namespace app\service\platform\auth;

use app\dao\system\admin\AdminDao;
use app\model\tenant\PlatformMenu;
use app\model\system\admin\Admin;
use app\platform\event\system\LoginLogEvent;
use app\platform\event\system\MenuBadgeDecorateEvent;
use core\foundation\base\BaseService;
use core\foundation\tool\MenuVariableParser;
use core\security\jwt\enum\ClientType;
use core\security\jwt\JwtToken;
use Illuminate\Database\Eloquent\Collection;
use support\Container;
use app\service\admin\system\MenuService;

class AuthService extends BaseService
{

    public function __construct(AdminDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 用户登录
     *
     * @param array $data
     * @return array
     * @throws \Exception
     */
    public function login(array $data): array
    {
        $userName = $data['user_name'] ?? '';
        $password = $data['password'] ?? '';

        if (empty($userName) || empty($password)) {
            throw new \Exception('用户名和密码不能为空', 400);
        }

        $admin = $this->dao->query()
            ->where(function ($query) use ($userName) {
                $query->where('user_name', $userName)
                    ->orWhere('mobile_phone', $userName)
                    ->orWhere('email', $userName);
            })
            ->whereHas('adminTypes', function ($query) {
                $query->whereIn('code', ['platform', 'root']);
            })
            ->first();

        if (!$admin) {
            $this->emitLoginFailedEvent(['user_name' => $userName], '用户不存在');
            throw new \Exception('用户不存在', 401);
        }

        if ($admin->enabled !== 1) {
            $this->emitLoginFailedEvent($admin->toArray(), '用户已被禁用');
            throw new \Exception('用户已被禁用', 401);
        }

        if (!password_verify($password, $admin->password)) {
            $this->emitLoginFailedEvent($admin->toArray(), '密码错误');
            throw new \Exception('密码错误', 401);
        }

        $this->updateLoginInfo($admin);

        $jwtToken = new JwtToken();
        $token = $jwtToken->generate(
            (string)$admin->id,
            ClientType::PLATFORM->value,
            [
                'user_name'  => $admin->user_name,
                'real_name'  => $admin->real_name,
                'is_super'   => $admin->is_super,
                'types'      => $admin->getTypes(),
            ]
        );

        $tokenData = [
            'access_token' => $token->accessToken,
            'refresh_token' => $token->refreshToken,
            'expires_in' => $token->expiresIn,
            'expires_at' => $token->expiresAt->getTimestamp(),
            'user_info' => [
                'id' => $admin->id,
                'user_name' => $admin->user_name,
                'real_name' => $admin->real_name,
                'is_super' => $admin->is_super,
                'mobile_phone' => $admin->mobile_phone,
                'email' => $admin->email,
            ],
        ];

        $this->emitLoginSuccessEvent(array_merge($tokenData, $tokenData['user_info']));

        return $tokenData;
    }

    /**
     * 更新登录信息
     *
     * @param Admin $admin
     */
    protected function updateLoginInfo(Admin $admin): void
    {
        $admin->login_ip = request()->getRealIp();
        $admin->login_time = time();
        $admin->save();
    }

    /**
     * 获取用户信息
     *
     * @param string $userId
     * @return array|null
     */
    public function getUserInfo(string $userId): ?array
    {
        error_log("[platform] getUserInfo userId={$userId}");
        $admin = $this->dao->get($userId);
        if (!$admin) {
            error_log("[platform] getUserInfo failed, admin not found for userId={$userId}");
            return null;
        }
        error_log("[platform] getUserInfo found admin id={$admin->id}");

        return [
            'id' => $admin->id,
            'user_name' => $admin->user_name,
            'real_name' => $admin->real_name,
            'nick_name' => $admin->nick_name,
            'is_super' => $admin->is_super,
            'mobile_phone' => $admin->mobile_phone,
            'email' => $admin->email,
            'avatar' => $admin->avatar,
            'enabled' => $admin->enabled,
            'login_ip' => $admin->login_ip,
            'login_time' => $admin->login_time,
            'created_at' => $admin->created_at,
            'updated_at' => $admin->updated_at,
        ];
    }

    /**
     * 获取用户菜单
     * 从 saas_template_menu(app='platform') 查询平台端菜单
     *
     * @param string $userId
     * @param bool $includeButtons
     * @return array
     * @throws \Exception
     */
    public function getMenusByUser(string $userId, bool $includeButtons = false): array
    {
        $types = $includeButtons ? [1, 2, 3, 4] : [1, 2];

        $menus = PlatformMenu::where('app', 'platform')
            ->where('enabled', 1)
            ->whereIn('type', $types)
            ->orderBy('sort', 'asc')
            ->get()
            ->toArray();

        $tree = $this->buildPlatformMenuTree($menus);

        // 触发徽标装饰事件：业务监听器可为菜单追加/覆盖徽标
        $event = new MenuBadgeDecorateEvent($tree, $userId, 'platform');
        $event->dispatch();

        return $event->menus;
    }

    /**
     * 构建平台菜单树（前端 RouteRecordStringComponent 格式）
     */
    private function buildPlatformMenuTree(array $items, int|string $parentId = 0): array
    {
        $tree = [];
        foreach ($items as $item) {
            if ((int)$item['pid'] === (int)$parentId) {
                $node = [
                    'name' => $this->getMenuName($item),
                    'path' => $item['path'],
                    'meta' => [
                        'title' => $item['title'],
                        'icon'  => $item['icon'] ?: '',
                    ],
                ];

                if (!empty($item['redirect'])) {
                    $node['redirect'] = $item['redirect'];
                }

                if ($item['type'] == 2 && !empty($item['component'])) {
                    $node['component'] = $item['component'];
                }

                if (!empty($item['is_affix'])) {
                    $node['meta']['affixTab'] = true;
                }

                $node['meta']['order'] = (int)$item['sort'];

                // 徽标配置来自 menu.variable 根级的 badge 域（未配置则不下发任何徽标字段）
                // 输出 snake_case，位置在根级，与 admin 端契约一致
                $badge = MenuVariableParser::badge($item['variable'] ?? '');
                if ($badge !== null) {
                    $node['badge']          = $badge['badge'];
                    $node['badge_type']     = $badge['badge_type'];
                    $node['badge_variants'] = $badge['badge_variants'];
                }

                // is_tab=0 → 隐藏标签页（与 admin 端语义一致）
                if (isset($item['is_tab']) && (int)$item['is_tab'] === 0) {
                    $node['meta']['hideInTab'] = true;
                }

                $children = $this->buildPlatformMenuTree($items, (int)$item['id']);
                if (!empty($children)) {
                    $node['children'] = $children;
                }

                $tree[] = $node;
            }
        }
        return $tree;
    }

    /**
     * 从菜单记录生成 name（路由标识，必须唯一）
     * 优先使用 code，否则从 path 最后一段生成驼峰名称
     */
    private function getMenuName(array $item): string
    {
        if (!empty($item['code'])) {
            return $item['code'];
        }
        $parts = array_values(array_filter(explode('/', trim($item['path'], '/')), 'strlen'));
        if (empty($parts)) {
            return 'PlatformRoot';
        }
        $name = end($parts);
        return str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $name)));
    }

    /**
     * 获取模拟菜单数据（临时）
     *
     * @return array
     */
    private function getMockMenus(): array
    {
        return [
            [
                'name' => 'Dashboard',
                'path' => '/',
                'redirect' => '/workspace',
                'meta' => [
                    'title' => '首页',
                    'icon' => 'ant-design:home-outlined',
                    'order' => -1,
                ],
                'children' => [
                    [
                        'name' => 'Analytics',
                        'path' => '/analytics',
                        'component' => '/dashboard/analytics/index',
                        'meta' => [
                            'title' => '分析页',
                            'icon' => 'ant-design:fund-filled',
                        ],
                    ],
                    [
                        'name' => 'Workspace',
                        'path' => '/workspace',
                        'component' => '/dashboard/workspace/index',
                        'meta' => [
                            'title' => '工作台',
                            'icon' => 'ant-design:appstore-outlined',
                            'affixTab' => true,
                        ],
                    ],
                ],
            ],
            // ==================== 租户管理 ====================
            [
                'name' => 'TenantManagement',
                'path' => '/tenant',
                'redirect' => '/tenant/list',
                'meta' => [
                    'title' => '租户管理',
                    'icon' => 'ant-design:team-outlined',
                    'order' => 10,
                ],
                'children' => [
                    [
                        'name' => 'TenantList',
                        'path' => '/tenant/list',
                        'component' => '/tenant/index',
                        'meta' => [
                            'title' => '租户列表',
                            'icon' => 'ant-design:unordered-list-outlined',
                        ],
                    ],
                    [
                        'name' => 'TenantSubscription',
                        'path' => '/tenant/subscription',
                        'component' => '/tenant/subscription/index',
                        'meta' => [
                            'title' => '套餐管理',
                            'icon' => 'ant-design:gift-outlined',
                        ],
                    ],
                ],
            ],
            // ==================== 数据中心 ====================
            [
                'name' => 'DataCenter',
                'path' => '/database',
                'meta' => [
                    'title' => '数据中心',
                    'icon' => 'ant-design:database-outlined',
                    'order' => 20,
                ],
                'children' => [
                    [
                        'name' => 'DbSource',
                        'path' => '/database',
                        'component' => '/database/index',
                        'meta' => [
                            'title' => '数据源管理',
                            'icon' => 'ant-design:cloud-server-outlined',
                        ],
                    ],
                    [
                        'name' => 'MenuTemplate',
                        'path' => '/template/menu',
                        'component' => '/template/menu/index',
                        'meta' => [
                            'title' => '菜单模板',
                            'icon' => 'ant-design:menu-outlined',
                        ],
                    ],
                    [
                        'name' => 'PlatformMenu',
                        'path' => '/system/menu',
                        'component' => '/system/menu/index',
                        'meta' => [
                            'title' => '平台菜单',
                            'icon' => 'ant-design:appstore-outlined',
                        ],
                    ],
                ],
            ],
            // ==================== 日志管理 ====================
            [
                'name' => 'LogManagement',
                'path' => '/monitor',
                'redirect' => '/monitor/login-log',
                'meta' => [
                    'title' => '日志管理',
                    'icon' => 'ant-design:file-text-outlined',
                    'order' => 30,
                ],
                'children' => [
                    [
                        'name' => 'LoginLog',
                        'path' => '/monitor/login-log',
                        'component' => '/monitor/login-log/index',
                        'meta' => [
                            'title' => '登录日志',
                            'icon' => 'ant-design:login-outlined',
                        ],
                    ],
                    [
                        'name' => 'OperateLog',
                        'path' => '/monitor/operation-log',
                        'component' => '/monitor/operation-log/index',
                        'meta' => [
                            'title' => '操作日志',
                            'icon' => 'ant-design:swap-outlined',
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * 获取用户菜单ID列表
     *
     * @param Admin $admin
     * @return array
     */
    private function getUserMenuIds(Admin $admin): array
    {
        $menuIds = [];

        $roles = $admin->roles()->with('menus')->get();
        foreach ($roles as $role) {
            foreach ($role->menus as $menu) {
                $menuIds[] = $menu->id;
            }
        }

        return array_unique($menuIds);
    }

    /**
     * 根据IDS获取菜单
     *
     * @param MenuService $menuService
     * @param array $ids
     * @param bool $includeButtons
     * @return Collection
     * @throws \Exception
     */
    private function getMenusByIds(MenuService $menuService, array $ids, bool $includeButtons = false): Collection
    {
        if (empty($ids)) {
            return new Collection();
        }

        $menuModel = $menuService->dao->getModel();
        $types = $includeButtons ? [1, 2, 3, 4] : [1, 2];

        return $menuModel
            ->whereIn('id', $ids)
            ->where('enabled', 1)
            ->whereIn('type', $types)
            ->orderBy('sort')
            ->get();
    }

    /**
     * 格式化菜单为树形结构
     *
     * @param Collection $menus
     * @return array
     */
    private function formatMenus(Collection $menus): array
    {
        $menuArray = $menus->toArray();
        return $this->buildTree($menuArray);
    }

    /**
     * 构建树形结构
     *
     * @param array $items
     * @param string|null $parentId
     * @return array
     */
    private function buildTree(array $items, ?string $parentId = null): array
    {
        $tree = [];

        foreach ($items as $item) {
            if ($item['parent_id'] == $parentId) {
                $children = $this->buildTree($items, $item['id']);
                if (!empty($children)) {
                    $item['children'] = $children;
                }
                $tree[] = $item;
            }
        }

        return $tree;
    }

    /**
     * 获取用户权限码列表
     *
     * @param string $userId
     * @return array
     */
    public function getPermissions(string $userId): array
    {
        $admin = $this->dao->get($userId);
        if (!$admin) {
            return [];
        }

        if (boolval($admin->is_super)) {
            return ['*'];
        }

        $permissions = [];
        $roles = $admin->roles()->with('menus')->get();

        foreach ($roles as $role) {
            foreach ($role->menus as $menu) {
                if (!empty($menu->permission)) {
                    $permissions[] = $menu->permission;
                }
            }
        }

        return array_unique($permissions);
    }

    /**
     * 更新用户基本信息
     *
     * @param string $userId
     * @param array $data 支持：real_name, nick_name, email, mobile_phone, avatar
     * @return array
     */
    public function updateUserInfo(string $userId, array $data): array
    {
        $allowedFields = ['real_name', 'nick_name', 'email', 'mobile_phone', 'avatar'];
        $updateData = array_intersect_key($data, array_flip($allowedFields));

        if (empty($updateData)) {
            throw new \RuntimeException('没有可更新的字段');
        }

        $affected = $this->dao->update($userId, $updateData);
        if (!$affected) {
            throw new \RuntimeException('用户不存在');
        }

        return $this->getUserInfo($userId);
    }

    /**
     * 修改密码
     *
     * @param string $userId
     * @param string $oldPassword
     * @param string $newPassword
     */
    public function changePassword(string $userId, string $oldPassword, string $newPassword): void
    {
        // 用 query 获取模型，避免 adminDao->get() 的额外动态属性
        $admin = $this->dao->query()->where('id', $userId)->first();
        if (!$admin) {
            throw new \RuntimeException('用户不存在');
        }

        if (!password_verify($oldPassword, $admin->password)) {
            throw new \RuntimeException('旧密码错误');
        }

        $affected = $this->dao->update($userId, [
            'password' => password_hash($newPassword, PASSWORD_BCRYPT),
        ]);
        if (!$affected) {
            throw new \RuntimeException('用户不存在');
        }
    }

    /**
     * 退出登录
     *
     * @param string|null $token
     * @return bool
     */
    public function logout(?string $token = null): bool
    {
        $jwtToken = new JwtToken();
        return $jwtToken->logout($token);
    }

    /**
     * 登录成功-事件发起
     *
     * @param array $tokenData
     */
    private function emitLoginSuccessEvent(array $tokenData): void
    {
        $loginIp = request()->getRealIp();
        $event = new LoginLogEvent(
            '登录成功',
            request()->app,
            $loginIp,
            $this->getIpLocation($loginIp),
            $this->getBrowser(request()->header('user-agent', '')),
            $this->getOs(request()->header('user-agent', '')),
            1,
            '登录成功',
            $tokenData['user_name'],
            $tokenData['id'],
            time(),
            $tokenData['access_token'],
            $tokenData['expires_at'] ?? time()
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
            $adminInfo['user_name'] ?? '未知',
            $adminInfo['id'] ?? 0,
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
        return '未知';
    }

    /**
     * 刷新Token
     *
     * @param string|null $refreshToken
     * @return array
     * @throws \core\jwt\ex\JwtException
     */
    public function refreshToken(?string $refreshToken = null): array
    {
        $jwtToken = new JwtToken();
        $token = $jwtToken->refresh($refreshToken);

        return [
            'access_token' => $token->accessToken,
            'refresh_token' => $token->refreshToken,
            'expires_in' => $token->expiresIn,
            'expires_at' => $token->expiresAt->getTimestamp(),
        ];
    }
}
