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
namespace core\business\plugin\traits;

use core\io\uuid\Snowflake;
use core\business\tenant\context\TenantContext;
use support\Db;

/**
 * 菜单操作 Trait
 */
trait MenuTrait
{
    /**
     * 预加载系统菜单的 code 映射（用于智能关联父级）
     *
     * @var array
     */
    protected array $_admin_menu_codes = [];
    protected array $_web_menu_codes = [];

    /**
     * 加载菜单文件（支持平台/租户上下文）
     *
     * 平台/无租户 → sys_menu / web_menu（主库）
     * 租户FIELD   → sys_tenant_menu（主库，含 tenant_id）
     * 租户DB      → sys_tenant_menu（租户独立库）
     */
    protected function loadMenus(): void
    {
        // 兼容两种菜单目录约定: resource/data/menu(脚手架生成) 与 resource/menu(历史),
        // 与 TenantPluginService::findPluginMenuFile / PluginLifecycleOrchestrator::loadMenuManifest 保持一致
        $menuDir = $this->findPluginMenuDir();
        if ($menuDir === null) {
            $this->output("📂 No menu directory found, skipping...");
            return;
        }

        $this->output("📂 Loading menus from: {$menuDir}");

        // 仅"单体模式"在 PluginInstall 阶段落菜单。
        // 多租户(含 SaaS 平台安装)的菜单统一由「租户安装模块」(TenantMenuSyncService)负责:
        //   - 平台安装插件 = 仅"上架", 不写菜单(菜单在租户安装/开通时同步到该租户 sys_menu)
        //   - 租户安装插件 = 由 PluginInstallService / installForTenant 调用 TenantMenuSyncService 写入该租户 sys_menu
        // 旧逻辑写入 sys_tenant_menu 的方式已废弃(详见 TenantMenuSyncService 注释)。
        if (!TenantContext::isSingleMode()) {
            $this->output("  ℹ️ Multi-tenant mode: plugin menus are installed by tenant install module, skip direct import");
            return;
        }

        // 单体模式：写入 sys_menu / web_menu（主库）
        // 先清理本插件已有菜单，避免重复安装/更新时残留
        $this->clearPluginMenus($this->pluginName);
        $this->preloadSystemMenuCodes('admin');
        $this->preloadSystemMenuCodes('web');
        $this->loadMenuFile($menuDir . '/admin.php', 'admin');
        $this->loadMenuFile($menuDir . '/web.php', 'web');
    }

    /**
     * 查找插件菜单目录（兼容两种约定）
     *
     * 1. resource/data/menu  —— 插件脚手架(PluginDevelopService)生成路径
     * 2. resource/menu       —— 历史约定
     */
    protected function findPluginMenuDir(): ?string
    {
        $candidates = [
            $this->pluginPath . '/resource/data/menu',
            $this->pluginPath . '/resource/menu',
        ];
        foreach ($candidates as $dir) {
            if (is_dir($dir)) {
                return $dir;
            }
        }
        return null;
    }

    /**
     * 加载租户菜单文件到 sys_tenant_menu
     */
    protected function loadTenantMenuFiles(string $menuDir, ?string $connection): void
    {
        $this->loadTenantMenuFile($menuDir . '/admin.php', 'admin', $connection);
        $this->loadTenantMenuFile($menuDir . '/web.php', 'web', $connection);
    }

    /**
     * 加载单个租户菜单文件
     */
    protected function loadTenantMenuFile(string $filePath, string $type, ?string $connection): void
    {
        if (!file_exists($filePath)) {
            $this->output("  ⚠️ {$type}: Tenant menu file not found");
            return;
        }

        $menu = include $filePath;
        if (empty($menu) || !is_array($menu)) {
            $this->output("  ⚠️ {$type}: Empty or invalid menu file");
            return;
        }

        $this->saveTenantMenuToTable($type, $menu, $connection);

        $this->output("  ✅ {$type}: Tenant menu loaded (" . count($menu) . " items)");
    }

    /**
     * 保存租户菜单到 sys_tenant_menu 表
     */
    protected function saveTenantMenuToTable(string $type, array $menu, ?string $connection): void
    {
        $tableName = 'sys_tenant_menu';

        $hasTable = Db::connection($connection)->getSchemaBuilder()->hasTable($tableName);
        if (!$hasTable) {
            $this->output("  ⚠️ Table {$tableName} does not exist, skipping tenant menu import");
            return;
        }

        $tenantId = $this->tenantId;
        if (empty($tenantId)) {
            $this->output("  ⚠️ Tenant ID is empty, skipping tenant menu import");
            return;
        }

        $this->insertTenantMenuItems($tableName, $menu, $this->pluginName, $tenantId, 0, 1, $connection);
        $this->output("  ✅ {$type}: Tenant menu saved to {$tableName}");
    }

    /**
     * 插入租户菜单项
     */
    protected function insertTenantMenuItems(string $tableName, array $items, string $plugin, string|int $tenantId, int $parentId = 0, int $level = 1, ?string $connection = null): void
    {
        foreach ($items as $index => $item) {
            $menuId = (int)Snowflake::generate();

            if ($parentId === 0 && isset($item['pid_code'])) {
                $parentId = $this->findTenantMenuParentByCode($item['pid_code'], $tableName, $tenantId, $connection);
            }

            $normalizedItem = $this->normalizeMenuItem($item, $plugin, $level, $index);

            $data = [
                'id'         => $menuId,
                'pid'        => $parentId,
                'tenant_id'  => $tenantId,
                'app'        => $item['app'] ?? 'admin',
                'source'     => 'plugin:' . $plugin,
                'title'      => $normalizedItem['title'],
                'code'       => $normalizedItem['code'],
                'level'      => $level,
                'type'       => $normalizedItem['type'],
                'sort'       => $normalizedItem['sort'],
                'path'       => $normalizedItem['path'],
                'component'  => $normalizedItem['component'],
                'icon'       => $normalizedItem['icon'],
                'is_show'    => $normalizedItem['is_show'],
                'is_link'    => $normalizedItem['is_link'],
                'is_cache'   => $normalizedItem['is_cache'],
                'is_sync'    => $normalizedItem['is_sync'],
                'enabled'    => 1,
                'created_at' => time(),
                'updated_at' => time(),
            ];

            Db::connection($connection)->table($tableName)->insert($data);

            if (!empty($item['children'])) {
                $this->insertTenantMenuItems($tableName, $item['children'], $plugin, $tenantId, $menuId, $level + 1, $connection);
            }
        }
    }

    /**
     * 在租户菜单表中查找父级ID
     */
    protected function findTenantMenuParentByCode(string $code, string $tableName, string|int $tenantId, ?string $connection): int
    {
        $parent = Db::connection($connection)
            ->table($tableName)
            ->where('tenant_id', $tenantId)
            ->where('code', $code)
            ->first();

        return $parent ? (int)$parent->id : 0;
    }

    /**
     * 预加载系统菜单的 code 映射
     * 用于插件菜单关联到系统已有菜单
     *
     * @param string $type 菜单类型 (admin/web)
     */
    protected function preloadSystemMenuCodes(string $type): void
    {
        $tableName = $type === 'admin' ? 'sys_menu' : 'web_menu';
        $connection = $this->connection ?? null;

        try {
            // 获取系统中非插件来源的菜单 code -> id 映射
            $menus = Db::connection($connection)
                ->table($tableName)
                ->where('source', 'system')
                ->whereNotNull('code')
                ->where('code', '!=', '')
                ->select(['id', 'code', 'pid', 'type'])
                ->get()
                ->toArray();

            $key = $type === 'admin' ? '_admin_menu_codes' : '_web_menu_codes';
            $this->$key = [];

            foreach ($menus as $menu) {
                $code = $menu->code;
                $this->$key[$code] = [
                    'id'   => $menu->id,
                    'pid'  => $menu->pid,
                    'type' => $menu->type,
                ];
            }

            $this->output("  📋 Preloaded {$type} system menus: " . count($this->$key) . " codes");
        } catch (\Throwable $e) {
            $this->output("  ⚠️ Failed to preload {$type} system menus: {$e->getMessage()}");
        }
    }

    /**
     * 根据 pid_code 查找父级 ID
     *
     * @param string|null $pidCode 父级菜单的 code
     * @param string $type 菜单类型
     * @return int 父级 ID，0 表示顶级菜单
     */
    protected function findParentIdByCode(?string $pidCode, string $type): int
    {
        if (empty($pidCode)) {
            return 0;
        }

        $key = $type === 'admin' ? '_admin_menu_codes' : '_web_menu_codes';
        $codes = $this->$key ?? [];

        if (!isset($codes[$pidCode])) {
            return 0;
        }

        $parentInfo = $codes[$pidCode];

        // 如果父级是目录类型(type=1)，则返回其 ID
        // 否则作为顶级菜单
        if ($type === 'admin') {
            // admin: 1=目录 2=菜单 3=按钮
            return in_array($parentInfo['type'], [1]) ? $parentInfo['id'] : 0;
        } else {
            // web 菜单类型判断
            return $parentInfo['id'];
        }
    }

    /**
     * 加载单个菜单文件
     */
    protected function loadMenuFile(string $filePath, string $type): void
    {
        if (!file_exists($filePath)) {
            $this->output("  ⚠️ {$type}: Menu file not found");
            return;
        }

        $menu = include $filePath;

        if (empty($menu)) {
            $this->output("  ⚠️ {$type}: Empty menu file");
            return;
        }

        if (!is_array($menu)) {
            $this->output("  ❌ {$type}: Invalid menu format");
            return;
        }

        $this->saveMenuToSystem($type, $menu);

        $this->output("  ✅ {$type}: Menu loaded (" . count($menu) . " items)");
    }

    /**
     * 保存菜单到系统
     */
    protected function saveMenuToSystem(string $type, array $menu): void
    {
        if ($type === 'admin') {
            $this->saveAdminMenu($menu);
        } else {
            $this->saveWebMenu($menu);
        }
    }

    /**
     * 保存后台菜单
     */
    protected function saveAdminMenu(array $menu): void
    {
        $tableName = 'sys_menu';

        $this->output("  📝 Saving admin menu to {$tableName}...");

        try {
            $connection = $this->connection ?? null;
            $hasTable = Db::connection($connection)->getSchemaBuilder()->hasTable($tableName);

            if (!$hasTable) {
                $this->output("  ⚠️ Table {$tableName} does not exist, skipping menu import");
                return;
            }

            $this->insertAdminMenuItems($tableName, $menu, $this->pluginName);

            $this->output("  ✅ Admin menu saved to {$tableName}");
        } catch (\Throwable $e) {
            $this->output("  ❌ Failed to save admin menu: {$e->getMessage()}");
        }
    }

    /**
     * 插入后台菜单项
     *
     * @param string $tableName 表名
     * @param array $items 菜单项
     * @param string $plugin 插件标识
     * @param int $parentId 父级ID
     * @param int $level 当前层级
     */
    protected function insertAdminMenuItems(string $tableName, array $items, string $plugin, int $parentId = 0, int $level = 1): void
    {
        $connection = $this->connection ?? null;

        foreach ($items as $index => $item) {
            $menuId = (int)Snowflake::generate();

            // 智能获取父级 ID（仅顶级菜单使用 pid_code）
            // 子菜单通过 parentId（children 嵌套）确定父级
            if ($parentId === 0 && isset($item['pid_code'])) {
                $parentId = $this->findParentIdByCode($item['pid_code'], 'admin');
            }

            // 规范化菜单数据：自动生成缺失的 code
            $normalizedItem = $this->normalizeMenuItem($item, $plugin, $level, $index);

            $data = [
                'id'         => $menuId,
                'pid'        => $parentId,
                'app'        => $item['app'] ?? 'admin',
                'source'     => 'plugin:' . $plugin,
                'title'      => $normalizedItem['title'],
                'code'       => $normalizedItem['code'],
                'level'      => $level,
                'type'       => $normalizedItem['type'],
                'sort'       => $normalizedItem['sort'],
                'path'       => $normalizedItem['path'],
                'component'  => $normalizedItem['component'],
                'icon'       => $normalizedItem['icon'],
                'is_show'   => $normalizedItem['is_show'],
                'is_link'   => $normalizedItem['is_link'],
                'is_cache'  => $normalizedItem['is_cache'],
                'is_sync'   => $normalizedItem['is_sync'],
                'created_at' => time(),
                'updated_at' => time(),
            ];

            Db::connection($connection)->table($tableName)->insert($data);

            // 记录本次插入菜单的 code -> id 映射（供子菜单使用）
            if ($normalizedItem['code']) {
                $this->_admin_menu_codes[$normalizedItem['code']] = [
                    'id'   => $menuId,
                    'pid'  => $parentId,
                    'type' => $data['type'],
                ];
            }

            // 递归处理子菜单，层级 +1
            if (!empty($item['children'])) {
                $this->insertAdminMenuItems($tableName, $item['children'], $plugin, $menuId, $level + 1);
            }
        }
    }

    /**
     * 规范化菜单项数据
     * 确保必要字段存在，自动生成缺失的 code
     *
     * @param array $item 菜单项
     * @param string $plugin 插件标识
     * @param int $level 当前层级
     * @param int $index 当前索引
     * @return array 规范化后的菜单项
     */
    protected function normalizeMenuItem(array $item, string $plugin, int $level, int $index): array
    {
        // 获取菜单名称
        $title = $item['name'] ?? $item['title'] ?? '';
        // 获取路径
        $path = $item['path'] ?? '';

        // 自动生成 code：如果没有 code 字段，根据插件名和路径生成
        $code = $item['code'] ?? '';
        if (empty($code)) {
            // 从 path 生成 code: /test-plugin/index -> test-plugin:index
            if (!empty($path)) {
                $code = trim($path, '/');
                $code = str_replace('/', ':', $code);
            }
            // 如果路径也为空，使用插件名+层级+索引生成唯一 code
            if (empty($code)) {
                $code = $plugin . ':level' . $level . '_' . $index;
            }
        }

        return [
            'title'     => $title,
            'code'      => $code,
            'type'      => $item['type'] ?? ($level === 1 ? 1 : 2), // 1=目录 2=菜单
            'sort'      => $item['sort'] ?? 0,
            'path'      => $path,
            'component' => $item['component'] ?? ($level === 1 ? '/layout' : ''),
            'icon'      => $item['icon'] ?? '',
            'is_show'   => $item['is_show'] ?? 1,
            'is_link'   => $item['is_link'] ?? 0,
            'is_cache'  => $item['is_cache'] ?? 0,
            'is_sync'   => $item['is_sync'] ?? 0,
        ];
    }

    /**
     * 保存前台菜单
     */
    protected function saveWebMenu(array $menu): void
    {
        $tableName = 'web_menu';

        $this->output("  📝 Saving web menu to {$tableName}...");

        try {
            $connection = $this->connection ?? null;
            $hasTable = Db::connection($connection)->getSchemaBuilder()->hasTable($tableName);

            if (!$hasTable) {
                $this->output("  ⚠️ Table {$tableName} does not exist, skipping menu import");
                return;
            }

            $this->insertWebMenuItems($tableName, $menu, $this->pluginName);

            $this->output("  ✅ Web menu saved to {$tableName}");
        } catch (\Throwable $e) {
            $this->output("  ❌ Failed to save web menu: {$e->getMessage()}");
        }
    }

    /**
     * 插入前台菜单项
     *
     * @param string $tableName 表名
     * @param array $items 菜单项
     * @param string $plugin 插件标识
     * @param int $parentId 父级ID
     * @param int $level 当前层级
     */
    protected function insertWebMenuItems(string $tableName, array $items, string $plugin, int $parentId = 0, int $level = 1): void
    {
        $connection = $this->connection ?? null;

        foreach ($items as $index => $item) {
            $menuId = (int)Snowflake::generate();

            // 智能获取父级 ID（仅顶级菜单使用 pid_code）
            // 子菜单通过 parentId（children 嵌套）确定父级
            if ($parentId === 0 && isset($item['pid_code'])) {
                $parentId = $this->findParentIdByCode($item['pid_code'], 'web');
            }

            // 规范化菜单数据：自动生成缺失的 code
            $normalizedItem = $this->normalizeWebMenuItem($item, $plugin, $level, $index);

            // target: 1=当前窗口 2=新窗口
            $targetValue = 1; // 默认当前窗口
            if (isset($item['target'])) {
                $targetValue = $item['target'] === '_self' || $item['target'] === 1 || $item['target'] === '1' ? 1 : 2;
            }

            $data = [
                'id'         => $menuId,
                'app'        => $item['app'] ?? 'web',
                'category'   => $item['category'] ?? '1',
                'source'     => 'plugin:' . $plugin,
                'code'       => $normalizedItem['code'],
                'is_public'  => $normalizedItem['is_public'],
                'is_no_auth' => $normalizedItem['is_no_auth'],
                'name'       => $normalizedItem['name'],
                'url'        => $normalizedItem['url'],
                'pid'        => $parentId,
                'level'      => $level,
                'type'       => $normalizedItem['type'],
                'sort'       => $normalizedItem['sort'],
                'target'     => $targetValue,
                'icon'       => $normalizedItem['icon'],
                'is_show'   => $normalizedItem['is_show'],
                'enabled'   => $normalizedItem['enabled'],
                'created_at' => time(),
                'updated_at' => time(),
            ];

            Db::connection($connection)->table($tableName)->insert($data);

            // 记录本次插入菜单的 code -> id 映射（供子菜单使用）
            if ($normalizedItem['code']) {
                $this->_web_menu_codes[$normalizedItem['code']] = [
                    'id'   => $menuId,
                    'pid'  => $parentId,
                    'type' => $data['type'],
                ];
            }

            // 递归处理子菜单，层级 +1
            if (!empty($item['children'])) {
                $this->insertWebMenuItems($tableName, $item['children'], $plugin, $menuId, $level + 1);
            }
        }
    }

    /**
     * 规范化前台菜单项数据
     * 确保必要字段存在，自动生成缺失的 code
     *
     * @param array $item 菜单项
     * @param string $plugin 插件标识
     * @param int $level 当前层级
     * @param int $index 当前索引
     * @return array 规范化后的菜单项
     */
    protected function normalizeWebMenuItem(array $item, string $plugin, int $level, int $index): array
    {
        // 获取菜单名称
        $name = $item['name'] ?? $item['title'] ?? '';
        // 获取 URL
        $url = $item['path'] ?? $item['url'] ?? '';

        // 自动生成 code：如果没有 code 字段，根据插件名和 URL 生成
        $code = $item['code'] ?? '';
        if (empty($code)) {
            // 从 url 生成 code
            if (!empty($url)) {
                $code = trim($url, '/');
                $code = str_replace('/', ':', $code);
            }
            // 如果 URL 也为空，使用插件名+层级+索引生成唯一 code
            if (empty($code)) {
                $code = $plugin . ':level' . $level . '_' . $index;
            }
        }

        return [
            'name'      => $name,
            'code'      => $code,
            'url'       => $url,
            'type'      => $item['type'] ?? 1,
            'sort'      => $item['sort'] ?? 0,
            'icon'      => $item['icon'] ?? '',
            'is_show'   => $item['is_show'] ?? 1,
            'is_public' => $item['is_public'] ?? 0,
            'is_no_auth' => $item['is_no_auth'] ?? 0,
            'enabled'   => $item['enabled'] ?? 1,
        ];
    }

    /**
     * 清除插件菜单（支持平台/租户上下文）
     *
     * 平台/无租户 → 清空 sys_menu/web_menu 中 source=plugin 的菜单
     * 租户FIELD   → 清空 sys_tenant_menu 中 tenant_id=? AND source=plugin
     * 租户DB      → 清空租户库 sys_tenant_menu 中 source=plugin
     */
    protected function clearPluginMenus(string $plugin): void
    {
        $connection = $this->connection ?? null;

        if ($this->isPlatformContext() || TenantContext::isSingleMode()) {
            // 平台/无租户：清空系统菜单中的插件菜单
            $this->clearMenusByTable('sys_menu', $plugin, $connection);
            $this->clearMenusByTable('web_menu', $plugin, $connection);
        } else {
            // 租户：清空租户菜单
            $this->clearTenantPluginMenus($plugin, $connection);
        }
    }

    /**
     * 清空系统菜单（source = 'plugin:{plugin}'）
     */
    protected function clearMenusByTable(string $tableName, string $plugin, ?string $connection): void
    {
        try {
            if (!Db::connection($connection)->getSchemaBuilder()->hasTable($tableName)) {
                return;
            }

            $deleted = Db::connection($connection)
                ->table($tableName)
                ->where('source', 'plugin:' . $plugin)
                ->delete();

            if ($deleted > 0) {
                $this->output("  🗑️ Cleared {$deleted} menus from {$tableName}");
            }
        } catch (\Throwable $e) {
            $this->output("  ⚠️ Failed to clear {$tableName}: {$e->getMessage()}");
        }
    }

    /**
     * 清空租户插件菜单
     *
     * FIELD模式：WHERE tenant_id = ? AND source = 'plugin:{plugin}'
     * DB模式：在租户独立库中 WHERE source = 'plugin:{plugin}'
     */
    protected function clearTenantPluginMenus(string $plugin, ?string $connection): void
    {
        $tableName = 'sys_tenant_menu';

        try {
            if (!Db::connection($connection)->getSchemaBuilder()->hasTable($tableName)) {
                return;
            }

            $query = Db::connection($connection)->table($tableName)->where('source', 'plugin:' . $plugin);

            // FIELD模式：追加 tenant_id 过滤（DB模式在独立库里，无需tenant_id过滤）
            if (TenantContext::getIsolationMode() === 'field' && $this->tenantId) {
                $query->where('tenant_id', $this->tenantId);
            }

            $deleted = $query->delete();

            if ($deleted > 0) {
                $this->output("  🗑️ Cleared {$deleted} tenant plugin menus");
            }
        } catch (\Throwable $e) {
            $this->output("  ⚠️ Failed to clear tenant plugin menus: {$e->getMessage()}");
        }
    }

    /**
     * 导入插件菜单（快捷方法）
     */
    public function importPluginMenus(): void
    {
        $this->importMenus();
    }

    /**
     * 卸载插件菜单（快捷方法）
     */
    public function uninstallPluginMenus(): void
    {
        $this->clearMenus();
    }

    /**
     * 导入菜单（子类可重写）
     */
    protected function importMenus(): void
    {
        $this->loadMenus();
    }

    /**
     * 清除菜单（子类可重写）
     */
    protected function clearMenus(): void
    {
        $this->clearPluginMenus($this->pluginName);
    }
}
