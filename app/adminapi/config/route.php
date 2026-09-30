<?php
declare(strict_types=1);

/**
 * This file is part of webman.
 * Licensed under The MIT License
 * For full copyright and license information, please see the MIT-LICENSE.txt
 *
 * @author    walkor<walkor@workerman.net>
 * @copyright walkor<walkor@workerman.net>
 * @link      http://www.workerman.net/
 * @license   http://www.opensource.org/licenses/mit-license.php MIT License
 */

use Webman\Route;
use WebmanTech\Swagger\Swagger;
use OpenApi\Annotations as OA;
/**
 * 注册admin APP路由
 */
Route::group('/adminapi', function () {
    // Swagger 注解路由注册（自动扫描）
    Swagger::create()->registerRoute([
        'route_prefix'   => '/openapi',
        'register_route' => true,
        'openapi_doc'    => [
            'scan_path' => [
                base_path('app/schema'),//基础schema
                base_path('app/adminapi'),//后端接口
                base_path('app/install'),//安装接口
            ],
            'modify'    => function (OA\OpenApi $openapi) {
                $openapi->info->title   = config('app.name') . ' API';
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
                        'name'           => config('madong.jwt.app.token_name', 'Authorization'),
                        'in'             => 'header',
                    ]),
                ];
            },
        ],
    ]);
});
