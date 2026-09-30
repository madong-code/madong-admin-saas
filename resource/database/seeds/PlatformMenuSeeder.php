<?php
/**
 * 平台运营端菜单种子（仅多租户模式执行）
 */

declare(strict_types=1);
namespace resource\database\seeds;


use app\model\tenant\PlatformMenu;
use core\io\uuid\Snowflake;
use Illuminate\Database\Seeder;


class PlatformMenuSeeder extends Seeder
{
    public function run(): void
    {
        // 仅在多租户模式下执行平台菜单的种子
        if (!config('tenant.enable', false)) {
            return;
        }

        $menus = include base_path('resource/data/menu/platform.php');

        foreach ($menus as $menu) {
            $this->insertMenu($menu, 0);
        }
    }

    /**
     * 递归插入菜单
     */
    private function insertMenu(array $menu, int|string $pid): void
    {
        $menuModel = new PlatformMenu();
        $menuModel->id        = Snowflake::generate();
        $menuModel->pid       = $pid;
        $menuModel->app       = $menu['app'] ?? 'platform';
        $menuModel->title     = $menu['title'] ?? '';
        $menuModel->code      = $menu['code'] ?? '';
        $menuModel->level     = $menu['level'] ?? null;
        $menuModel->type      = $menu['type'] ?? 1;
        $menuModel->sort      = $menu['sort'] ?? 0;
        $menuModel->path      = $menu['path'] ?? '';
        $menuModel->component = $menu['component'] ?? '';
        $menuModel->redirect  = $menu['redirect'] ?? '';
        $menuModel->icon      = $menu['icon'] ?? '';
        $menuModel->is_show   = $menu['is_show'] ?? 1;
        $menuModel->is_link   = $menu['is_link'] ?? 0;
        $menuModel->enabled   = $menu['enabled'] ?? 1;
        $menuModel->is_cache  = $menu['is_cache'] ?? 0;
        $menuModel->is_affix  = $menu['is_affix'] ?? 0;
        $menuModel->save();

        // 递归插入子菜单
        if (!empty($menu['children'])) {
            foreach ($menu['children'] as $child) {
                $this->insertMenu($child, $menuModel->id);
            }
        }
    }
}
