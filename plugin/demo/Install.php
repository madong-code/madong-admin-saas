<?php
declare(strict_types=1);

namespace plugin\demo;

use core\business\plugin\PluginInstall as BasePluginInstall;

/**
 * 系统日志查看器插件安装类
 *
 * 安装时导入：
 * - 后端菜单 (resource/data/menu/admin.php)  -> 导入「日志查看器」菜单与按钮权限
 * - 前端模板资源 (resource/template/admin/)  -> 复制到前端 template/admin/src/plugin/logviewer/
 *
 * 卸载时清理：
 * - 卸载后端菜单 / 删除前端模板资源
 */
class Install extends BasePluginInstall
{
    /**
     * 安装后回调：导入菜单 + 复制前端模板
     */
    protected function afterInstall(string $version): void
    {
        $this->output("  🔄 [demo] importing backend menus...\n");
        $this->importPluginMenus();

        $this->output("  🔄 [demo] copying frontend templates...\n");
        $this->importTemplates();
    }

    /**
     * 卸载后回调：清理菜单 + 删除模板
     */
    protected function afterUninstall(string $version): void
    {
        $this->output("  🔄 [demo] removing backend menus...\n");
        $this->uninstallPluginMenus();

        $this->output("  🔄 [demo] removing frontend templates...\n");
        $this->deletePluginTemplates();
    }
}
