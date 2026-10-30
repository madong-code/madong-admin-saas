# 安装与卸载

`demo` 插件的安装/卸载逻辑集中在 `Install.php`，其继承了框架基类 `core\business\plugin\PluginInstall`，仅需实现两个回调钩子。

```php
class Install extends BasePluginInstall
{
    protected function afterInstall(string $version): void
    {
        $this->importPluginMenus();   // 导入后台菜单 + 按钮权限
        $this->importTemplates();     // 复制前端模板到主工程
    }

    protected function afterUninstall(string $version): void
    {
        $this->uninstallPluginMenus();     // 清理后台菜单
        $this->deletePluginTemplates();    // 删除已复制的前端模板
    }
}
```

---

## 1. 安装流程

在插件管理后台点击「安装」后，框架会依次执行：

1. **数据库迁移**：执行 `resource/database/migrations/` 下的迁移，创建 `{表前缀}demo_demo_test` 表（详见 [database.md](./database.md)）。
2. **数据填充**：执行 `resource/database/seeds/DemoTestSeeder.php`，向测试表写入 10 条示例数据（若表已非空则跳过）。
3. **菜单导入**：`importPluginMenus()` 读取 `resource/data/menu/admin.php`，把「示例演示」菜单树与权限码同步进后台 `sys_menu`（`source = plugin:demo`，菜单 code 以 `demo` 开头）。
4. **模板复制**：`importTemplates()` 将 `resource/template/admin/` 复制到主工程前端 `template/admin/src/plugin/demo/`，`resource/template/web/` 复制到 `template/web/src/plugin/demo/`。

> 安装过程中会在控制台输出进度提示（如 `🔄 [demo] importing backend menus...`）。

---

## 2. 卸载流程

点击「卸载」后，框架执行：

1. `uninstallPluginMenus()`：删除 `source = plugin:demo` 的全部菜单与权限。
2. `deletePluginTemplates()`：删除主工程中对应的 `plugin/demo` 前端目录。
3. **删表**：由于 `config/info.php` 中 `uninstall.drop_tables = true`，插件表 `demo_demo_test` 会被一并删除。

### 卸载策略（`config/info.php`）
| 配置项 | 值 | 含义 |
| --- | --- | --- |
| `drop_tables` | `true` | 卸载时删除 `demo_demo_test` 数据表（数据不可恢复） |
| `remove_dependencies` | `false` | 卸载时不移除 composer / npm 依赖 |
| `undeletable` | `false` | 允许卸载后从系统中移除 |

> ⚠️ 卸载前请确保 `demo_demo_test` 中无重要数据，删表操作不可逆。

---

## 3. 重新安装

卸载后再安装会重新执行上述流程。由于种子逻辑在 `count > 0` 时跳过，若表被 `drop_tables` 删除，则重新安装时会再次填充 10 条示例数据。

---

## 4. 常见问题

- **菜单重复？** 本插件前端 `routes/index.ts` 刻意留空（不声明静态路由），菜单完全由后端 `sys_menu` 下发。若把静态路由 `name` 写死（如 `DemoRoot`）会与后端下发的 code（`demo`）不一致，导致侧边栏重复渲染，请勿在插件内重复定义静态路由。
- **模板没生效？** 确认 `importTemplates()` 已执行且目标前端工程路径正确；模板复制为文件级覆盖，重新安装可触发。
- **`Install.php` 注释与实际不符？** 文件头注释提到的"系统日志查看器"为旧版遗留文案，实际行为仅做菜单导入与模板复制，不影响功能。
