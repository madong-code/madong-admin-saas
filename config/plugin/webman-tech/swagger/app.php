<?php

use OpenApi\Annotations as OAA;

return [
    /**
     * 自定义路由注册器
     * 对路由排序，静态路由优先于变量路由，避免 FastRoute 冲突
     *
     * @see WebmanTech\Swagger\RouteAnnotation\Reader::getData()
     * @see \core\business\route\SwaggerRouteRegister
     */
    'route_factory'  => '\\core\\business\\route\\SwaggerRouteRegister',

    /**
     * 全局开关
     */
    'enable'         => true,

    /**
     * 全局扫描的配置
     *
     * @see \WebmanTech\Swagger\Swagger::registerGlobalRoute()
     * @see \WebmanTech\Swagger\DTO\ConfigRegisterRouteDTO
     */
    'global_route'   => [
        'register_route' => false,
        'enable'         => false,
        'openapi_doc'    => [
            'scan_path' => [
            ],
        ],
    ],

    /**
     * 全局的 host forbidden 配置
     *
     * @see \WebmanTech\Swagger\DTO\ConfigHostForbiddenDTO
     */
    'host_forbidden' => [
        'enable'          => true,
        'host_white_list' => [],
    ],

    /**
     * 全局的 swagger ui 配置
     *
     * @see \WebmanTech\Swagger\DTO\ConfigSwaggerUiDTO
     */
    'swagger_ui'     => [

    ],

    /**
     * 全局的 openapi doc 配置
     *
     * @see \WebmanTech\Swagger\DTO\ConfigOpenapiDocDTO
     */
    'openapi_doc'    => [
        'scan_path'    => function () {
        },
        'scan_exclude' => [
        ],
        'modify'       => function (OAA\OpenApi $openapi) {
        },
    ],
];
