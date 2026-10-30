# 菜单与权限

后台菜单定义在 `resource/data/menu/admin.php`，安装时由 `importPluginMenus()` 同步进系统菜单表（`sys_menu`），`source = plugin:demo`，权限 code 以 `demo` 开头。

> `resource/data/menu/web.php` 当前为空数组（`return [];`），前台菜单暂未定义。

---

## 1. 字段约定

| 字段 | 含义 |
| --- | --- |
| `app` | 所属端，固定 `admin` |
| `title` | 菜单显示名称 |
| `code` | 权限码（如 `demo:crud:basic`），用于菜单同步定位 |
| `type` | `1` = 目录（含子级），`2` = 菜单（叶子页面） |
| `sort` | 同级排序 |
| `path` | 前端路由路径，如 `/demo/crud/basic` |
| `component` | 视图组件路径，相对插件视图目录 `src/plugin/demo/views/`。后端会从 `source='plugin:demo'` 自动注入 `meta.module='demo'`，前端据此解析为 `/plugin/demo/views/{component}`，**无需手写 module 前缀** |
| `redirect` | 目录重定向目标 |
| `icon` | 图标（lucide 图标名，如 `lucide:flask-conical`） |
| `is_show` | 是否显示（`1` 显示） |
| `is_cache` | `1` 表示 keepAlive 缓存（对应原路由 `meta.keepAlive`） |
| `is_sync` | `1` 表示参与同步 |
| `methods` | 请求方法，菜单项通常为 `GET` |

---

## 2. 菜单树

```
示例演示  (目录, code=demo, /demo, icon=flask-conical)
├─ CRUD 示例  (目录, demo:crud, /demo/crud)
│  ├─ 基础表格      (菜单, demo:crud:basic,        component=crud/basic)
│  ├─ 树形表格      (菜单, demo:crud:tree,         component=crud/tree)
│  └─ 抽屉模式      (菜单, demo:crud:drawer,       component=crud/drawer)
├─ 表单示例  (目录, demo:form, /demo/form)
│  ├─ 表单总览      (菜单, demo:form:overview,     component=form/overview)
│  ├─ 基础表单      (菜单, demo:form:basic,        component=form/basic)
│  ├─ 查询表单      (菜单, demo:form:query,        component=form/query)
│  ├─ 表单校验      (菜单, demo:form:rules,        component=form/rules)
│  ├─ 动态表单      (菜单, demo:form:dynamic,      component=form/dynamic)
│  ├─ 表单API       (菜单, demo:form:api,          component=form/api)
│  ├─ 合并表单      (菜单, demo:form:merge,        component=form/merge)
│  └─ 自定义布局    (菜单, demo:form:custom-layout, component=form/custom-layout)
├─ Vxe 表格  (目录, demo:vxe-table, /demo/vxe-table)
│  ├─ 基础表格      (菜单, demo:vxe-table:basic,       component=vxe-table/basic)
│  ├─ 远程加载      (菜单, demo:vxe-table:remote,      component=vxe-table/remote)
│  ├─ 树形表格      (菜单, demo:vxe-table:tree,        component=vxe-table/tree)
│  ├─ 自定义单元格  (菜单, demo:vxe-table:custom-cell,  component=vxe-table/custom-cell)
│  ├─ 单元格编辑    (菜单, demo:vxe-table:edit-cell,   component=vxe-table/edit-cell)
│  ├─ 行编辑        (菜单, demo:vxe-table:edit-row,    component=vxe-table/edit-row)
│  ├─ 固定表头/列   (菜单, demo:vxe-table:fixed,       component=vxe-table/fixed)
│  ├─ 搜索表单      (菜单, demo:vxe-table:form,        component=vxe-table/form)
│  └─ 虚拟滚动      (菜单, demo:vxe-table:virtual,     component=vxe-table/virtual)
└─ 控件中心  (目录, demo:widgets, /demo/widgets)
   ├─ 表单设计器    (菜单, demo:widgets:form-builder,  component=widgets/form-builder/builder/index)
   ├─ 流程设计器    (菜单, demo:widgets:visual-flow,   component=widgets/visual-flow/index, is_cache=1)
   ├─ 图片选择器    (菜单, demo:widgets:image-selector,component=widgets/image-selector/index)
   ├─ 弹窗          (菜单, demo:widgets:modal,         component=widgets/modal/index, is_cache=1)
   ├─ 抽屉          (菜单, demo:widgets:drawer,        component=widgets/drawer/index, is_cache=1)
   ├─ 滑块验证      (菜单, demo:widgets:captcha,       component=widgets/captcha/slider-captcha)
   ├─ 文本省略      (菜单, demo:widgets:ellipsis,      component=widgets/ellipsis/index)
   ├─ JsonViewer    (菜单, demo:widgets:json-viewer,   component=widgets/json-viewer/index)
   ├─ Loading       (菜单, demo:widgets:loading,       component=widgets/loading/index)
   ├─ CountTo       (菜单, demo:widgets:count-to,      component=widgets/count-to/index)
   ├─ 图片裁剪      (菜单, demo:widgets:cropper,       component=widgets/cropper/index)
   ├─ 上下文菜单    (菜单, demo:widgets:context-menu,  component=widgets/context-menu/index)
   ├─ 拖动调整      (菜单, demo:widgets:resize,        component=widgets/resize/basic)
   ├─ Tippy         (菜单, demo:widgets:tippy,         component=widgets/tippy/index)
   ├─ 富文本编辑器  (菜单, demo:widgets:tiptap,        component=widgets/tiptap/index)
   ├─ Motion        (菜单, demo:widgets:motion,        component=widgets/motion/index)
   └─ 多语言示例    (菜单, demo:widgets:locale,        component=widgets/locale/index)
```

---

## 3. 新增/修改菜单

1. 在 `resource/data/menu/admin.php` 对应层级增加菜单项数组。
2. 为页面（叶子）设置 `type=2`、`component` 为相对 `views/` 的路径（不含 `plugin/demo/views/` 前缀）。
3. 为目录（父级）设置 `type=1`、`component=''` 并配置 `redirect`。
4. 保证 `code` 全局唯一且以 `demo` 开头，便于同步定位。
5. 重新安装（或触发菜单同步）后生效；卸载会一并清除 `source=plugin:demo` 的全部菜单。

> 本插件**不声明前端静态路由**（`routes/index.ts` 为空）。菜单与路由完全由后端 `sys_menu` 下发，避免静态路由 `name` 与后端 code 不一致导致的侧边栏重复渲染。
