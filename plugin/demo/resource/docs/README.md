# demo 示例演示插件

> 一款面向调试与测试的示例插件，用于演示 madong 插件系统的目录规范、安装/卸载流程、菜单与权限同步、前端模板注入，以及数据库迁移/种子等常见能力。

- **插件标识**：`demo`（identifier：`demo`）
- **插件类型**：`madong:app`
- **版本**：`1.0.0`
- **作者**：Mr.April
- **官网**：https://madong.tech

---

## 1. 它是什么

`demo` 是一个"样板插件"，并非业务功能插件。它把插件开发中会用到的关键机制都跑通了一遍，方便开发者：

- 安装插件后，自动在后台注入一套完整的「示例演示」菜单（CRUD、表单、Vxe 表格、控件中心）；
- 自动把前端模板复制到主工程的前端源码目录，无需手动拷贝；
- 自动创建并填充一张测试数据表 `demo_demo_test`，供列表/表单示例使用；
- 提供 OpenAPI（Swagger）路由注册示例，可一行切换到真实后端接口。

> 当前版本的后端仅包含安装逻辑与配置，**未提供真实后端 Controller**（详见 [api.md](./api.md) 的"已知限制"）。所有示例页面默认使用前端 `mock` 数据驱动，便于直接预览交互效果。

---

## 2. 目录结构

```
backend/plugin/demo/
├── Install.php                  # 安装/卸载回调（继承 core\business\plugin\PluginInstall）
├── app/                         # 后端应用层（当前为空骨架）
│   ├── adminapi/                # 后台 API：controller / schema / validate（空）
│   ├── api/                     # 开放 API：controller / schema / validate（空）
│   ├── dao/  model/  service/   # 数据访问 / 模型 / 服务层（空）
│   └── readme.txt
├── config/                      # 插件配置
│   ├── info.php                 # 插件元信息（名称/版本/卸载策略）
│   ├── app.php                  # 应用配置（标题/调试开关等）
│   ├── route.php                # OpenAPI / Swagger 路由注册
│   ├── exception.php            # 异常处理类
│   └── translation.php          # 多语言配置
└── resource/                    # 资源（安装时导入）
    ├── data/
    │   ├── config/              # 配置数据（当前为空）
    │   └── menu/                # 菜单数据
    │       ├── admin.php        # 后台菜单树（核心）
    │       └── web.php          # 前台菜单（当前为空）
    ├── database/
    │   ├── migrations/          # 数据库迁移
    │   │   └── 2026_06_16_000001_create_demo_test_table.php
    │   └── seeds/               # 数据填充
    │       └── DemoTestSeeder.php
    └── template/                # 前端模板（安装时复制到主工程）
        ├── admin/               # 后台前端模板
        │   ├── routes/index.ts  # 前端静态路由（当前为空，菜单由后端下发）
        │   ├── mock/            # 本地 mock 数据
        │   ├── views/           # 示例页面（crud / form / vxe-table / widgets）
        │   └── lang/            # 多语言（zh-CN / en-US）
        └── web/                 # 前台（开放端）前端模板
            ├── index.ts  page.vue
            ├── pages/  components/  stores/  types/  lang/  api/
            └── ...
```

---

## 3. 功能清单

### 后台示例（`resource/template/admin/views`）
| 分组 | 示例页面 | 说明 |
| --- | --- | --- |
| CRUD 示例 | 基础表格 / 树形表格 / 抽屉模式 | 列表、树、抽屉编辑交互 |
| 表单示例 | 总览 / 基础 / 查询 / 校验 / 动态 / API / 合并 / 自定义布局 | 表单各类用法 |
| Vxe 表格 | 基础 / 远程加载 / 树形 / 自定义单元格 / 单元格编辑 / 行编辑 / 固定表头列 / 搜索表单 / 虚拟滚动 | VxeTable 高级能力 |
| 控件中心 | 表单设计器 / 流程设计器 / 图片选择器 / 弹窗 / 抽屉 / 滑块验证 / 文本省略 / JsonViewer / Loading / CountTo / 图片裁剪 / 上下文菜单 / 拖动调整 / Tippy / 富文本 / Motion / 多语言 | UI 组件画廊 |

### 前台示例（`resource/template/web`）
- 一个最小可运行的 Pinia Store 示例（`useTestPluginStore`）、两个组件（`CounterCard` / `MessageList`）、类型定义与多语言包，演示插件如何向前台导出能力。

---

## 4. 文档导航

- [安装与卸载](./install.md)
- [数据库说明](./database.md)
- [菜单与权限](./menu.md)
- [前端模板](./frontend.md)
- [接口与 OpenAPI](./api.md)

---

## 5. 注意事项（务必阅读）

1. **`web` 模板沿用了通用示例命名**：其内部 type/store/i18n key 仍使用 `test` 命名空间（如 `PLUGIN_NAME='test'`、`$t('test.*')`），`pages/index.vue` 则已改为从 `~/plugin/demo` 导入。若以本插件为脚手架二次开发，请全局替换命名空间保持一致。
2. **`Install.php` 文件头注释为旧版遗留**：注释中提到的"系统日志查看器"并非本插件真实功能，实际安装行为仅做"导入菜单 + 复制前端模板"，请以代码逻辑为准。
3. **后端无真实接口**：`app/api`、`app/adminapi` 下的 controller 目录为空，示例页面均由前端 `mock` 驱动；如需真实数据，请补充 Controller 并参考 [api.md](./api.md) 调整路由与数据源。
4. **卸载会删表**：`config/info.php` 中 `uninstall.drop_tables = true`，卸载插件时会删除 `demo_demo_test` 表，请注意备份。
