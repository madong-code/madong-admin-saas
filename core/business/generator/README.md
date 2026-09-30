# 代码生成器 Generator

基于配置驱动的 CRUD 代码生成器，通过解析表结构和配置，自动生成后端 PHP 代码和前端 Vue 代码。

---

## 目录结构

```
generator/
├── config/              # 示例配置文件
│   └── example.json     # 用户配置示例
├── factory/             # 工厂类
│   ├── GeneratorFactory.php   # 场景生成器 & 文件生成器工厂
├── file/                # 文件生成器（每种文件类型一个）
│   ├── AbstractFileGenerator.php   # 抽象基类
│   ├── ControllerGenerator.php     # 后端控制器
│   ├── ModelGenerator.php          # 后端模型
│   ├── ServiceGenerator.php        # 后端服务
│   ├── DaoGenerator.php            # 后端数据访问
│   ├── ValidateGenerator.php       # 后端验证器
│   ├── RequestFormGenerator.php    # 创建/更新请求 DTO
│   ├── RequestQueryGenerator.php   # 查询请求 DTO
│   ├── ResponseGenerator.php       # 响应 DTO
│   ├── RouteGenerator.php          # 路由配置（可选）
│   ├── MigrationGenerator.php      # 数据库迁移 SQL（可选）
│   ├── ApiGenerator.php            # 前端 API 接口
│   ├── ApiModelGenerator.php       # 前端类型定义
│   ├── ViewGenerator.php           # 前端 CRUD 页面
│   ├── ViewSchemaGenerator.php     # 前端 Schema
│   └── LangGenerator.php           # 前端国际化
├── interfaces/          # 接口定义
│   ├── SceneGeneratorInterface.php   # 场景生成器接口
│   └── FileGeneratorInterface.php    # 文件生成器接口
├── scene/               # 场景生成器
│   ├── BackendSceneGenerator.php     # 后端场景路径
│   └── FrontendSceneGenerator.php    # 前端场景路径（admin/web/h5 等共用）
├── stubs/               # 模板文件
│   ├── server/          # 后端服务端模板
│   └── admin/           # admin 前端模板
│   └── web/             # web 前端模板
├── utils/               # 工具类
│   ├── ConfigParser.php     # 配置解析器
│   ├── PathResolver.php     # 路径解析器
│   └── TemplateRenderer.php # 模板渲染器
├── GeneratorEngine.php  # 生成器引擎（入口）
└── README.md            # 本文档
```

---

## 配置文件

系统定义在 `core/business/config/generator.php`，按需修改。

### `scene_types` — 场景类型定义

每个场景定义三个属性：

| 属性 | 说明 | 示例 |
|------|------|------|
| `label` | 显示名称 | `'管理端'` |
| `default_sub_path` | 模板目录下的子路径 | `'apps/admin'` |
| `template_type` | **模板目录名**（空=后端） | `'mono'` / `'web'` |

`template_type` 直接作为 `template/` 下的目录名：

| `template_type` | 生成目录 |
|----------------|---------|
| `''` (空) | `backend/app/...`（后端） |
| `'mono'` | `template/mono/...` |
| `'web'` | `template/web/...` |
| `'h5'` | `template/h5/...` |

### `template_types` — 模板类型定义

| 属性 | 说明 |
|------|------|
| `label` | 显示名称 |
| `supported_scenes` | 该模板类型支持哪些 scene_type（用于向后兼容验证） |

### `file_types_map` — 文件类型映射

每种场景生成哪些文件：

```php
'backend' => ['controller', 'model', 'service', 'dao', 'validate', 'request_form', 'request_query', 'response'],
'admin'   => ['api', 'api_model', 'view', 'view_schema', 'lang'],
```

> `migration` 和 `route` 默认不生成，需要时在用户配置中手动添加。

### `default_scene_types` — 默认生成场景

```php
'default_scene_types' => ['backend', 'admin'],
```

---

## 用户配置格式

来自数据库记录或 JSON，例如：

```json
{
    "table_name": "sys_admin_dept",
    "module_name": "sys_admin_dept",
    "class_name": "sys_admin_dept",
    "basic": {
        "table_name": "sys_admin_dept",
        "module_name": "sys_admin_dept",
        "class_name": "sys_admin_dept"
    },
    "columns": [
        { "column_name": "id", "column_type": "int", "is_pk": 1, ... }
    ],
    "scene_types": ["backend", "admin"],
    "file_types_map": {
        "backend": ["controller", "model", "service", ...]
    }
}
```

### 关键字段

| 字段 | 说明 | 优先级 |
|------|------|--------|
| `module_name` | 模块名（决定目录名） | 最高 |
| `table_name` | 数据库表名（兜底目录名） | 次之 |
| `class_name` | 类名（自动驼峰化） | - |
| `columns` | 表字段列表 | - |
| `scene_types` | 要生成的场景列表 | 覆盖默认值 |
| `scene_type` | 单场景（向后兼容） | 无 `scene_types` 时生效 |
| `file_types_map` | 自定义文件类型映射 | 覆盖默认值 |
| `template_type` | 全局模板类型 | `'mono'` |
| `template_sub_path` | 全局子路径 | 由场景定义自动推导 |

---

## 场景与目录映射

| 场景 | `template_type` | `default_sub_path` | 生成目录 |
|------|----------------|-------------------|---------|
| `backend` 后端 | `''` (空) | `''` | `backend/app/...` |
| `admin` 管理端 | `'mono'` | `'apps/admin'` | `template/mono/apps/admin/src/...` |
| `platform` 平台端 | `'mono'` | `'apps/platform'` | `template/mono/apps/platform/src/...` |
| `web` 会员端 | `'web'` | `''` | `template/web/src/...` |
| `h5` H5 | `'h5'` | `''` | `template/h5/src/...` |

---

## 后端文件类型

每个类型对应一个 FileGenerator 和 stub 模板：

| 文件类型 | 生成器 | 路径示例 | 依赖 `columns` |
|---------|--------|---------|:---:|
| `controller` | ControllerGenerator | `controller/{module}/{Class}Controller.php` | 否 |
| `model` | ModelGenerator | `model/{module}/{Class}.php` | 否 |
| `service` | ServiceGenerator | `service/admin/{module}/{Class}Service.php` | 否 |
| `dao` | DaoGenerator | `dao/{module}/{Class}Dao.php` | 否 |
| `validate` | ValidateGenerator | `validate/{module}/{Class}Validate.php` | 是 |
| `request_form` | RequestFormGenerator | `schema/request/{module}/{Class}FormRequest.php` | 是 |
| `request_query` | RequestQueryGenerator | `schema/request/{module}/{Class}QueryRequest.php` | 是 |
| `response` | ResponseGenerator | `schema/response/{module}/{Class}Response.php` | 是 |
| `migration` | MigrationGenerator | `migrations/{module}/create_{module}_table.sql` | **是** |
| `route` | RouteGenerator | `route/{module}/{module}.php` | 否 |

> `migration` 和 `route` 默认不生成，需要时在 `file_types_map` 中手动添加。

## 前端文件类型

| 文件类型 | 生成器 | 路径示例 |
|---------|--------|---------|
| `api` | ApiGenerator | `api/{module}/index.ts` |
| `api_model` | ApiModelGenerator | `api/{module}/types.ts` |
| `view` | ViewGenerator | `views/{module}/index.vue` |
| `view_schema` | ViewSchemaGenerator | `views/{module}/schemas/index.tsx` |
| `lang` | LangGenerator | `lang/zh-cn/{module}.json` |

前端模块名自动转为连字符格式（`sys_admin_dept` → `sys-admin-dept`）。

---

## 新增场景示例

在 `core/business/config/generator.php` 中加三项即可：

```php
// 1. scene_types
'scene_types' => [
    'mobile' => [
        'label' => '移动端',
        'default_sub_path' => '',
        'template_type' => 'mobile',    // template/mobile/ 目录
    ],
],

// 2. template_types
'template_types' => [
    'mobile' => [
        'label' => '移动端',
        'supported_scenes' => ['mobile'],
    ],
],

// 3. file_types_map
'file_types_map' => [
    'mobile' => ['api', 'api_model', 'view', 'view_schema', 'lang'],
],
```

使用时：
```json
{ "scene_types": ["backend", "admin", "mobile"] }
```

---

## 架构流程

```
GeneratorEngine (入口)
    │
    ├─ ConfigParser → 解析用户配置 + 注入系统配置 (_scene_type_defs)
    │
    ├─ GeneratorFactory
    │      ├─ createSceneGenerator(type, config) → BackendSceneGenerator / FrontendSceneGenerator
    │      └─ createFileGenerator(type, config) → ControllerGenerator / ApiGenerator / ...
    │
    ├─ 遍历 scene_types：
    │      ├─ sceneGenerator.generateFilePath(fileType) → 绝对路径
    │      └─ fileGenerator.generateContent() → 文件内容
    │
    └─ 输出：preview (内容+路径) / deploy (写磁盘) / download (打包)
```

### 关键调用链

```
GeneratorEngine::preview()
  → scene_types 循环
    → Factory::createSceneGenerator(sceneType, config)
      → FrontendSceneGenerator 或 BackendSceneGenerator
    → Factory::createFileGenerator(fileType, config)
      → 具体的 FileGenerator 子类
    → fileGenerator.generateContent()     ← 生成文件内容
    → sceneGenerator.generateFilePath()   ← 生成文件路径
```
