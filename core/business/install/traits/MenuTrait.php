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
 * Official Website: https://madong.tech
 */

namespace core\business\install\traits;

use app\model\system\menu\Menu as SysMenu;
use app\model\tenant\MenuTemplate;
use app\model\web\Menu as WebMenu;
use app\model\tenant\WebMenuTemplate;
use core\io\uuid\Snowflake;

/**
 * 菜单导入 Trait
 */
trait MenuTrait
{

    /**
     * 运行菜单种子
     *
     * @param bool $enableTenant 是否启用多租户模式
     *        - true:  多租户模式，只写入 saas_template_menu，sys_menu 等创建租户时同步
     *        - false: 非租户模式，直接写入 sys_menu
     */
    public function runMenu(bool $enableTenant = false): void
    {
        $this->runWebMenu($enableTenant);

        if ($enableTenant) {
            // ==== 多租户模式 ====
            // admin + platform 菜单 → saas_template_menu
            // admin_saas.php（SaaS 专属菜单，如应用管理/模块市场）追加导入，避免重复清空 app='admin'
            $this->loadMenuIntoTemplate('admin');
            $this->loadMenuIntoTemplate('admin_saas', true);
            $this->loadMenuIntoTemplate('platform');
        } else {
            // ==== 非租户模式 ====
            // admin 菜单 → sys_menu
            $this->runAdminMenu();
        }
    }

    /**
     * 将 saas_template_menu（app='admin'）克隆到 sys_menu
     * 保留相同 ID，template_id = id，保证两个表 ID 一致
     */
    public function cloneTemplateToSysMenu(): void
    {
        $templateTable = $this->table((new MenuTemplate())->getTable());
        $menuTable = $this->table((new SysMenu())->getTable());

        // 1. 清空 sys_menu
        $this->getPdo()->exec("DELETE FROM `{$menuTable}` WHERE `app` = 'admin'");

        // 2. 从 saas_template_menu 克隆 app='admin' 到 sys_menu
        $sql = "INSERT INTO `{$menuTable}` (
                    `id`, `pid`, `template_id`, `app`, `source`, `title`, `code`, `level`,
                    `type`, `sort`, `path`, `component`, `redirect`, `icon`,
                    `is_show`, `is_link`, `link_url`, `enabled`, `open_type`,
                    `is_cache`, `is_sync`, `is_affix`, `variable`, `methods`,
                    `is_global`, `is_frame`,
                    `created_at`, `created_by`, `updated_at`, `updated_by`
                )
                SELECT
                    `id`, `pid`, `id` AS `template_id`, `app`, `source`, `title`, `code`, `level`,
                    `type`, `sort`, `path`, `component`, `redirect`, `icon`,
                    `is_show`, `is_link`, `link_url`, `enabled`, `open_type`,
                    `is_cache`, `is_sync`, `is_affix`, `variable`, `methods`,
                    `is_global`, `is_frame`,
                    `created_at`, `created_by`, `updated_at`, `updated_by`
                FROM `{$templateTable}`
                WHERE `app` = 'admin'";
        $this->getPdo()->exec($sql);
    }

    /**
     * 多租户模式：从数据文件加载菜单到 saas_template_menu
     *
     * @param string $app admin / platform
     */
    public function loadMenuIntoTemplate(string $app, bool $append = false): void
    {
        $menuFile = base_path("resource/data/menu/{$app}.php");
        if (!file_exists($menuFile)) {
            return;
        }

        $menus = require $menuFile;
        $table = $this->table((new MenuTemplate())->getTable());
        if (!$append) {
            // 非追加模式：先清空该 app 的模板菜单，再全量写入
            $this->getPdo()->exec("DELETE FROM `{$table}` WHERE `app` = '{$app}'");
        }

        foreach ($menus as $menu) {
            $this->insertMenuTemplate($table, $menu, 0);
        }
    }

    /**
     * 非租户模式：admin 菜单直接写入 sys_menu
     * 加载 admin.php（通用菜单） + admin_standalone.php（非租户专用菜单，如应用管理、代码生成等）
     */
    public function runAdminMenu(): void
    {
        $menuTable = $this->table((new SysMenu())->getTable());
        $this->getPdo()->exec("TRUNCATE TABLE `{$menuTable}`");

        $this->loadAdminMenuFile('resource/data/menu/admin.php', $menuTable);
        $this->loadAdminMenuFile('resource/data/menu/admin_standalone.php', $menuTable);
    }

    /**
     * 加载单个 admin 菜单文件到 sys_menu
     */
    private function loadAdminMenuFile(string $relativePath, string $menuTable): void
    {
        $menuFile = base_path($relativePath);
        if (!file_exists($menuFile)) {
            return;
        }

        $menus = require $menuFile;

        foreach ($menus as $menu) {
            $this->insertAdminMenu($menuTable, $menu, 0);
        }
    }

    /**
     * 非租户模式：递归插入 admin 菜单到 sys_menu
     */
    private function insertAdminMenu(string $tableName, array $menu, int|string $pid): int|string
    {
        $id = Snowflake::generate();

        $data = [
            'id' => $id,
            'pid' => $pid,
            'app' => $menu['app'] ?? 'admin',
            'source' => $menu['source'] ?? 'system',
            'title' => $menu['title'] ?? '',
            'code' => $menu['code'] ?? '',
            'level' => $this->getMenuLevel($pid),
            'type' => $menu['type'] ?? 1,
            'sort' => $menu['sort'] ?? 999,
            'path' => $menu['path'] ?? '',
            'component' => $menu['component'] ?? '',
            'redirect' => $menu['redirect'] ?? '',
            'icon' => $menu['icon'] ?? '',
            'is_show' => $menu['is_show'] ?? 1,
            'is_link' => $menu['is_link'] ?? 0,
            'link_url' => $menu['link_url'] ?? '',
            'enabled' => $menu['enabled'] ?? 1,
            'open_type' => $menu['open_type'] ?? 0,
            'is_cache' => $menu['is_cache'] ?? 0,
            'is_sync' => $menu['is_sync'] ?? 1,
            'is_affix' => $menu['is_affix'] ?? 0,
            'is_global' => $menu['is_global'] ?? 0,
            'variable' => $menu['variable'] ?? '',
            'methods' => strtolower($menu['methods'] ?? 'get'),
            'is_frame' => $menu['is_frame'] ?? 1,
            'created_at' => $this->currentTime,
            'created_by' => 0,
            'updated_at' => $this->currentTime,
            'updated_by' => 0,
        ];

        $this->insert($tableName, $data);

        if (!empty($menu['children'])) {
            foreach ($menu['children'] as $child) {
                $this->insertAdminMenu($tableName, $child, $id);
            }
        }
        return $id;
    }

    /**
     * 非租户模式：获取菜单层级（基于 sys_menu 已有记录计算）
     */
    private function getMenuLevel(int|string $pid): int
    {
        if ($pid == 0) return 1;
        $menuTable = $this->table((new SysMenu())->getTable());
        $stmt = $this->getPdo()->prepare("SELECT `level` FROM `{$menuTable}` WHERE id = ?");
        $stmt->execute([$pid]);
        $parent = $stmt->fetch();
        return ($parent['level'] ?? 1) + 1;
    }

    /**
     * 递归插入菜单模板
     */
    private function insertMenuTemplate(string $tableName, array $menu, int|string $pid): int|string
    {
        $id = Snowflake::generate();

        $data = [
            'id' => $id,
            'pid' => $pid,
            'app' => $menu['app'] ?? 'admin',
            'source' => 'template',
            'title' => $menu['title'] ?? '',
            'code' => $menu['code'] ?? '',
            'level' => $menu['level'] ?? null,
            'type' => $menu['type'] ?? 1,
            'sort' => $menu['sort'] ?? 999,
            'path' => $menu['path'] ?? '',
            'component' => $menu['component'] ?? '',
            'redirect' => $menu['redirect'] ?? '',
            'icon' => $menu['icon'] ?? '',
            'is_show' => $menu['is_show'] ?? 1,
            'is_link' => $menu['is_link'] ?? 0,
            'link_url' => $menu['link_url'] ?? '',
            'enabled' => $menu['enabled'] ?? 1,
            'open_type' => $menu['open_type'] ?? 0,
            'is_cache' => $menu['is_cache'] ?? 0,
            'is_sync' => $menu['is_sync'] ?? 1,
            'is_affix' => $menu['is_affix'] ?? 0,
            'is_global' => $menu['is_global'] ?? 0,
            'variable' => $menu['variable'] ?? '',
            'methods' => strtolower($menu['methods'] ?? 'get'),
            'is_frame' => $menu['is_frame'] ?? 1,
            'created_at' => $this->currentTime,
            'created_by' => 0,
            'updated_at' => $this->currentTime,
            'updated_by' => 0,
        ];

        $this->insert($tableName, $data);

        if (!empty($menu['children'])) {
            foreach ($menu['children'] as $child) {
                $this->insertMenuTemplate($tableName, $child, $id);
            }
        }
        return $id;
    }

    /**
     * 运行Web端菜单种子
     *
     * @param bool $enableTenant 是否启用多租户模式
     *        - true:  多租户模式，只写入 saas_template_web_menu，租户创建时同步
     *        - false: 非租户模式，直接写入 web_menu
     */
    public function runWebMenu(bool $enableTenant = false): void
    {
        $menuFile = base_path('resource/data/menu/web.php');
        if (!file_exists($menuFile)) {
            return;
        }

        $menus = require $menuFile;

        if ($enableTenant) {
            // ==== 多租户模式：写入前端菜单模板表 ====
            $table = $this->table((new WebMenuTemplate())->getTable());
            $this->getPdo()->exec("DELETE FROM `{$table}`");

            foreach ($menus as $menu) {
                $this->insertWebMenuTemplate($table, $menu, 0);
            }
        } else {
            // ==== 非租户模式：直接写入 web_menu ====
            $menuTable = $this->table((new WebMenu())->getTable());
            $this->getPdo()->exec("TRUNCATE TABLE `{$menuTable}`");

            foreach ($menus as $menu) {
                $this->insertWebMenu($menuTable, $menu, 0);
            }
        }
    }

    /**
     * 递归插入Web端菜单到 web_menu
     */
    private function insertWebMenu(string $tableName, array $menu, int|string $pid): int|string
    {
        $id = (int)Snowflake::generate();
        
        $data = [
            'id' => $id, 
            'pid' => $pid,
            'app' => $menu['app'] ?? 'web',
            'category' => $menu['category'] ?? 1,
            'source' => $menu['source'] ?? 'system',
            'code' => $menu['code'] ?? '',
            'name' => $menu['name'] ?? '',
            'url' => $menu['url'] ?? '',
            'icon' => $menu['icon'] ?? '',
            'level' => $menu['level'] ?? 1,
            'type' => $menu['type'] ?? 1,
            'sort' => $menu['sort'] ?? 999,
            'target' => $menu['target'] ?? 1,
            'is_show' => $menu['is_show'] ?? 1,
            'enabled' => $menu['enabled'] ?? 1,
            'created_at' => $this->currentTime,
            'updated_at' => $this->currentTime,
            'deleted_at' => null,
        ];

        $this->insert($tableName, $data);

        if (!empty($menu['children'])) {
            foreach ($menu['children'] as $child) {
                $this->insertWebMenu($tableName, $child, $id);
            }
        }
        
        return $id;
    }

    /**
     * 多租户模式：递归插入Web端菜单到 saas_template_web_menu
     */
    private function insertWebMenuTemplate(string $tableName, array $menu, int|string $pid): int|string
    {
        $id = (int)Snowflake::generate();

        $data = [
            'id' => $id,
            'pid' => $pid,
            'app' => $menu['app'] ?? 'web',
            'category' => $menu['category'] ?? 1,
            'source' => 'template',
            'code' => $menu['code'] ?? '',
            'is_public' => $menu['is_public'] ?? 0,
            'is_no_auth' => $menu['is_no_auth'] ?? 0,
            'name' => $menu['name'] ?? '',
            'url' => $menu['url'] ?? '',
            'icon' => $menu['icon'] ?? '',
            'level' => $menu['level'] ?? 1,
            'type' => $menu['type'] ?? 1,
            'sort' => $menu['sort'] ?? 999,
            'target' => $menu['target'] ?? 1,
            'extra' => isset($menu['extra']) ? json_encode($menu['extra'], JSON_UNESCAPED_UNICODE) : null,
            'is_show' => $menu['is_show'] ?? 1,
            'enabled' => $menu['enabled'] ?? 1,
            'created_at' => $this->currentTime,
            'created_by' => 0,
            'updated_at' => $this->currentTime,
            'updated_by' => 0,
        ];

        $this->insert($tableName, $data);

        if (!empty($menu['children'])) {
            foreach ($menu['children'] as $child) {
                $this->insertWebMenuTemplate($tableName, $child, $id);
            }
        }

        return $id;
    }
}
