<?php
/**
 * demo 插件路由配置
 * 使用 Swagger 注解自动生成路由
 */

use Webman\Route;
use WebmanTech\Swagger\Swagger;
use OpenApi\Annotations as OA;

/**
 * 注册 API 路由
 */
Route::group('/api', function () {
    Swagger::create()->registerRoute([
        'route_prefix'   => '/demo/openapi',
        'register_route' => true,
        'openapi_doc'    => [
            'scan_path' => [
                base_path('plugin/demo/app/api'),
            ],
            'modify'    => function (OA\OpenApi $openapi) {
                $openapi->info->title   = 'demo 示例演示 API';
                $openapi->info->version = '1.0.0';
                $openapi->servers       = [
                    new OA\Server(
                        [
                            'url'         => '/api',
                            'description' => request()->host(),
                        ]
                    ),
                ];
                /** @phpstan-ignore-next-line */
                if (!$openapi->components instanceof OA\Components) {
                    $openapi->components = new OA\Components([]);
                }
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

/**
 * 注册 AdminAPI 路由
 */
Route::group('/adminapi', function () {
    Swagger::create()->registerRoute([
        'route_prefix'   => '/demo/openapi',
        'register_route' => true,
        'openapi_doc'    => [
            'scan_path' => [
                base_path('plugin/demo/app/adminapi'),
            ],
            'modify'    => function (OA\OpenApi $openapi) {
                $openapi->info->title   = 'demo 示例演示 API';
                $openapi->info->version = '1.0.0';
                $openapi->servers       = [
                    new OA\Server(
                        [
                            'url'         => '/adminapi',
                            'description' => request()->host(),
                        ]
                    ),
                ];
                /** @phpstan-ignore-next-line */
                if (!$openapi->components instanceof OA\Components) {
                    $openapi->components = new OA\Components([]);
                }
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
