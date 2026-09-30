<?php
declare(strict_types=1);

/**
 * This file is part of webman.
 * Licensed under The MIT License
 * For full copyright and license information, please see the MIT-LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
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
 * 注册API路由
 */
Route::group('/api', function () {
    Swagger::create()->registerRoute([
        'route_prefix'   => '/openapi',
        'register_route' => true,
        'openapi_doc'    => [
            'scan_path' => [
                base_path('app/api'),
            ],
            'modify'    => function (OA\OpenApi $openapi) {
                $openapi->info->title   = config('app.name') . ' API';
                $openapi->info->version = '1.0.0';
                $openapi->servers       = [
                    new OA\Server(
                        [
                            'url'         => '/api',
                            'description' => 'API Server',
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
