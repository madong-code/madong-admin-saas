<?php
/**
 * 前端菜单种子
 */

declare(strict_types=1);
namespace resource\database\seeds;

use app\model\tenant\WebMenuTemplate;
use app\model\web\Menu;
use core\io\uuid\Snowflake;
use Illuminate\Database\Seeder;

class WebMenuSeeder extends Seeder
{
    public function run(): void
    {
        $menus = include base_path('resource/data/menu/web.php');

        if (config('tenant.enable', false)) {
            // 多租户模式：写入前端菜单模板表，由同步服务推送到租户
            $this->seedToWebMenuTemplate($menus);
        } else {
            // 单库模式：直接写入 web_menu
            $this->seedToWebMenu($menus);
        }
    }

    /**
     * 单库模式：写入 web_menu
     */
    private function seedToWebMenu(array $menus): void
    {
        Menu::truncate();

        foreach ($menus as $menu) {
            $this->insertMenu($menu, 0);
        }
    }

    /**
     * 多租户模式：写入 saas_template_web_menu
     */
    private function seedToWebMenuTemplate(array $menus): void
    {
        WebMenuTemplate::truncate();

        foreach ($menus as $menu) {
            $this->insertWebMenuTemplate($menu, 0);
        }
    }

    /**
     * 递归插入菜单到 web_menu
     */
    private function insertMenu(array $menu, int|string $pid): void
    {
        $menuModel = new Menu();
        $menuModel->id = Snowflake::generate();
        $menuModel->pid = $pid;
        $menuModel->app = $menu['app'] ?? 'web';
        $menuModel->category = $menu['category'] ?? 1;
        $menuModel->source = $menu['source'] ?? 'system';
        $menuModel->code = $menu['code'] ?? '';
        $menuModel->is_public = $menu['is_public'] ?? 0;
        $menuModel->name = $menu['name'] ?? '';
        $menuModel->url = $menu['url'] ?? '';
        $menuModel->icon = $menu['icon'] ?? '';
        $menuModel->level = $menu['level'] ?? 1;
        $menuModel->type = $menu['type'] ?? 1;
        $menuModel->sort = $menu['sort'] ?? 0;
        $menuModel->target = $menu['target'] ?? 1;
        $menuModel->is_show = $menu['is_show'] ?? 1;
        $menuModel->enabled = $menu['enabled'] ?? 1;
        $menuModel->created_at = $menu['created_at'] ?? time();
        $menuModel->updated_at = $menu['updated_at'] ?? time();
        $menuModel->deleted_at = $menu['deleted_at'] ?? null;
        $menuModel->save();

        if (!empty($menu['children'])) {
            foreach ($menu['children'] as $child) {
                $this->insertMenu($child, $menuModel->id);
            }
        }
    }

    /**
     * 递归插入菜单到 saas_template_web_menu
     */
    private function insertWebMenuTemplate(array $menu, int|string $pid): void
    {
        $model = new WebMenuTemplate();
        $model->id = Snowflake::generate();
        $model->pid = $pid;
        $model->app = $menu['app'] ?? 'web';
        $model->category = $menu['category'] ?? 1;
        $model->source = $menu['source'] ?? 'template';
        $model->code = $menu['code'] ?? '';
        $model->is_public = $menu['is_public'] ?? 0;
        $model->is_no_auth = $menu['is_no_auth'] ?? 0;
        $model->name = $menu['name'] ?? '';
        $model->url = $menu['url'] ?? '';
        $model->icon = $menu['icon'] ?? '';
        $model->level = $menu['level'] ?? 1;
        $model->type = $menu['type'] ?? 1;
        $model->sort = $menu['sort'] ?? 0;
        $model->target = $menu['target'] ?? 1;
        $model->extra = $menu['extra'] ?? null;
        $model->is_show = $menu['is_show'] ?? 1;
        $model->enabled = $menu['enabled'] ?? 1;
        $model->created_at = $menu['created_at'] ?? time();
        $model->updated_at = $menu['updated_at'] ?? time();
        $model->deleted_at = $menu['deleted_at'] ?? null;
        $model->save();

        if (!empty($menu['children'])) {
            foreach ($menu['children'] as $child) {
                $this->insertWebMenuTemplate($child, $model->id);
            }
        }
    }
}
