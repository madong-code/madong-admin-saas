<?php
declare(strict_types=1);

/**
 *+------------------
 * madong
 *+------------------
 * Copyright (c) https://gitee.com/motion-code  All rights reserved.
 *+------------------
 * Author: Mr. April (405784684@qq.com)
 *+------------------
 * Official Website: http://www.madong.tech
 */
use Webman\Route;
use WebmanTech\Swagger\Swagger;
use OpenApi\Annotations as OA;

//Route::group('/install', function () {
//    Swagger::create()->registerRoute([
//        'route_prefix'   => '/openapi',
//        'register_route' => true,
//        'openapi_doc'    => [
//            'scan_path' => [
//                base_path('app/install'),
//            ],
//            'modify'    => function (OA\OpenApi $openapi) {
//                $openapi->info->title   = config('app.name') . ' API';
//                $openapi->info->version = '1.0.0';
//                $openapi->servers       = [
//                    new OA\Server(
//                        [
//                            'url'         => '/install',
//                            'description' => request()->host(),
//                        ]
//                    ),
//                ];
//            },
//
//        ],
//    ]);
//});


// 安装模块API路由
//Route::any('/install/api', [app\install\controller\Index::class, 'index']);
