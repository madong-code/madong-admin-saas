<?php
/**
 * 菜单数据种子
 */

declare(strict_types=1);
namespace resource\database\seeds;


use app\model\system\menu\Menu;
use core\io\uuid\Snowflake;
use Illuminate\Database\Seeder;


class MenuSeeder extends Seeder
{
    public function run(): void
    {
        // 多租户模式（非 single）跳过租户业务数据
        if (config('tenant.enable', false)) {
            return;
        }

        // 清空表
        Menu::truncate();

        $menus = include base_path('resource/data/menu/admin.php');

        // 插入菜单数据
        foreach ($menus as $menu) {
            $this->insertMenu($menu, 0);
        }
    }

    /**
     * 递归插入菜单
     */
    private function insertMenu(array $menu, int|string $pid): void
    {
        $menuModel = new Menu();
        $menuModel->id = Snowflake::generate();
        $menuModel->pid = $pid;
        $menuModel->title = $menu['title'] ?? '';
        $menuModel->app = $menu['app'] ?? 'admin';
        $menuModel->code = $menu['code'] ?? '';
        $menuModel->icon = $menu['icon'] ?? '';
        $menuModel->sort = $menu['sort'] ?? 0;
        $menuModel->type = $menu['type'] ?? 1;
        $menuModel->is_show = $menu['is_show'] ?? 1;
        $menuModel->is_link = $menu['is_link'] ?? 0;
        $menuModel->is_cache = $menu['is_cache'] ?? 0;
        $menuModel->path = $menu['path'] ?? '';
        $menuModel->component = $menu['component'] ?? '';
        $menuModel->redirect = $menu['redirect'] ?? '';
        $menuModel->enabled = $menu['enabled'] ?? 1;
        $menuModel->save();

        // 递归插入子菜单
        if (!empty($menu['children'])) {
            foreach ($menu['children'] as $child) {
                $this->insertMenu($child, $menuModel->id);
            }
        }
    }
}
