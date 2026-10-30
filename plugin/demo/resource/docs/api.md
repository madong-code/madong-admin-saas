# 接口与 OpenAPI

`demo` 插件的路由与 API 文档由 `config/route.php` 统一注册，采用 Webman + `WebmanTech\Swagger` 的注解式（OpenAPI）自动生成方案。

---

## 1. 路由注册（`config/route.php`）

文件为两组路由分别注册 OpenAPI 文档：

| 分组 | 扫描目录 | OpenAPI 前缀 | server |
| --- | --- | --- | --- |
| `/api` | `plugin/demo/app/api` | `/demo/openapi` | `/api` |
| `/adminapi` | `plugin/demo/app/adminapi` | `/demo/openapi` | `/adminapi` |

示例（节选自 `/api` 组）：

```php
Route::group('/api', function () {
    Swagger::create()->registerRoute([
        'route_prefix'   => '/demo/openapi',
        'register_route' => true,
        'openapi_doc'    => [
            'scan_path' => [ base_path('plugin/demo/app/api') ],
            'modify'    => function (OA\OpenApi $openapi) {
                $openapi->info->title   = 'demo 示例演示 API';
                $openapi->info->version = '1.0.0';
                $openapi->servers       = [ new OA\Server([
                    'url'         => '/api',
                    'description' => request()->host(),
                ]) ];
                // 注册 apiKey 鉴权方案，header 名取自 core.jwt.app.token_name（默认 Authorization）
                $openapi->components->securitySchemes = [
                    new OA\SecurityScheme([
                        'securityScheme' => 'api_key',
                        'type'           => 'apiKey',
                        'name'           => config('core.jwt.app.token_name', 'Authorization'),
                        'in'             => 'header',
                    ]),
                ];
            },
        ],
    ]);
});
```

要点：
- 扫描路径为 `app/api`、`app/adminapi` 下的 PHP 文件，按 OpenAPI（`@OA\*`）注解生成接口文档。
- 文档访问前缀为 `/demo/openapi`（OpenAPI JSON 与 Swagger UI）。
- 鉴权方式：`apiKey`，位于请求头，字段名由 `config('core.jwt.app.token_name')` 决定，默认 `Authorization`。

---

## 2. 已知限制（重要）

⚠️ **当前 `app/api`、`app/adminapi` 下的 `controller/` 目录均为空**，没有任何带 `@OA\*` 注解的控制器。因此：

- 插件**未提供真实后端接口**，OpenAPI 扫描结果为空。
- 后台示例页面（CRUD / 表单 / Vxe 表格）默认使用前端 `mock` 数据（见 [frontend.md](./frontend.md)），并非真实接口返回。
- 这是一个"前端优先"的示例插件，用于演示菜单、模板注入与插件骨架。

### 如何补齐真实接口
1. 在 `app/api/controller`（或 `app/adminapi/controller`）新建 Controller，使用 `#[OA\...]` / `@OA\*` 注解描述接口。
2. 在 `app/api/validate`、`app/api/schema` 中补充校验器与数据结构（目录已预留）。
3. 如需数据访问，实现 `app/model`、`app/dao`、`app/service`（目录已预留）。
4. 在视图中将 `mock/api.ts` 的调用替换为对应后端接口地址（如需鉴权，请求头带上 `Authorization`）。
5. 重新加载路由 / 访问 `/demo/openapi` 即可看到自动生成的 API 文档。

---

## 3. 异常处理

`config/exception.php` 将全局异常处理指定为框架统一处理器：

```php
return [
    '' => \core\foundation\exception\Handler::class,
];
```

插件无需自定义异常类即可复用系统错误处理与响应格式。
