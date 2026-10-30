# 前端模板

`demo` 插件的前端资源位于 `resource/template/`，安装时由 `importTemplates()` 复制到主工程前端源码目录：

- `resource/template/admin/` → `template/admin/src/plugin/demo/`
- `resource/template/web/`    → `template/web/src/plugin/demo/`

---

## 1. 后台模板（`admin`）

### 1.1 静态路由（`routes/index.ts`）
当前刻意留空：

```ts
const routes: PluginRoute[] = [];
export default routes;
```

菜单与路由完全由后端 `sys_menu` 下发（见 [menu.md](./menu.md)）。若在此声明静态路由，其 `name` 需与后端菜单 `code` 完全一致，否则会被去重逻辑忽略或导致侧边栏重复。

### 1.2 Mock 数据（`mock/`）
| 文件 | 内容 |
| --- | --- |
| `table-data.ts` | 生成 `MOCK_TABLE_DATA`（40 条）、`MOCK_PRODUCT_DATA`、`MOCK_MENU_DATA` 等本地数据 |
| `api.ts` | 基于 mock 数据的伪接口：`getTableListApi` / `getExampleTableApi` / `getAllMenusApi` / `uploadFile`，均以 `Promise.resolve` 返回，模拟分页与异步 |

> 示例页面默认调用这些 mock 接口，无需后端即可预览真实交互（分页、排序、上传等）。

### 1.3 视图页面（`views/`）
| 目录 | 文件 | 演示点 |
| --- | --- | --- |
| `crud/` | `basic.vue` / `tree.vue` / `drawer.vue` | 列表 CRUD、树形、抽屉编辑 |
| `form/` | `overview` `basic` `query` `rules` `dynamic` `api` `merge` `custom-layout` | 表单各类用法与布局 |
| `vxe-table/` | `basic` `remote` `tree` `custom-cell` `edit-cell` `edit-row` `fixed` `form` `virtual` | VxeTable 高级能力 |
| `widgets/` | 表单设计器 / 流程设计器 / 图片选择器 / 弹窗 / 抽屉 / 滑块验证 / 文本省略 / JsonViewer / Loading / CountTo / 图片裁剪 / 上下文菜单 / 拖动调整 / Tippy / 富文本 / Motion / 多语言 | UI 组件画廊 |
| （根） | `doc-button.vue` | 文档按钮示例组件 |

页面基于项目通用能力：`#/adapter/crud`（`useCrud`）、`#/components/page`（`Page` 容器）、Element Plus 等。

### 1.4 多语言（`lang/`）
- `zh-CN/*.json`、`en-US/*.json` 各 39 个语言包，对应各示例模块。
- 由框架 i18n 自动加载，无需手动注册。

---

## 2. 前台模板（`web`）

一个最小可运行的插件前端样板，演示插件如何向前台"导出能力"。

### 2.1 入口（`index.ts`）
```ts
export type * from './types'
export { useTestPluginStore } from './stores'
export { default as TestCounterCard } from './components/CounterCard.vue'
export { default as TestMessageList } from './components/MessageList.vue'

export const PLUGIN_NAME = 'test'
export const PLUGIN_VERSION = '1.0.0'
```

其它模块：
- `page.vue`：插件根页面。
- `pages/index.vue`：示例页，导入 `TestCounterCard` / `TestMessageList` 并展示。
- `pages/routes.ts`：前台路由。
- `components/`：`CounterCard.vue`（计数卡片，Element Plus + store）、`MessageList.vue`（消息列表）。
- `stores/index.ts`：`useTestPluginStore`（Pinia），含 `count`、`messages` 状态与 `increment/decrement/reset/simulateAsync` 等 actions。
- `types/index.ts`：`TestPluginState` / `TestMessage` / `TestPluginConfig` 类型。
- `lang/`：`zh-cn/`、`en/` 多语言包。
- `api/`：当前为空（预留真实接口层）。

> **命名空间注意**：该模板由通用 `test` 示例插件改编而来，内部仍大量使用 `test` 命名（如 `PLUGIN_NAME='test'`、`$t('test.*')`、`useTestPluginStore`）。`pages/index.vue` 已改为从 `~/plugin/demo` 导入。若以本插件为脚手架，请统一将 `test` 替换为 `demo`，避免 i18n key 与目录不一致。

---

## 3. 修改与调试

- **改页面**：直接编辑 `resource/template/{admin,web}/...`，重新安装插件触发模板复制；或在主工程 `src/plugin/demo/` 中临时改后回填。
- **加页面**：新增 `.vue` 后，在 `resource/data/menu/admin.php` 增加对应菜单项（见 [menu.md](./menu.md)），并准备对应语言包。
- **切真实数据**：将视图中对 `mock/api.ts` 的调用替换为 `web/api/` 或后端真实接口（参考 [api.md](./api.md)）。
