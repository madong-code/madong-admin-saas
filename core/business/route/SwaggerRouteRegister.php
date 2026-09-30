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
namespace core\business\route;

use Webman\Route;
use WebmanTech\Swagger\Integrations\RouteRegisterInterface;
use WebmanTech\Swagger\RouteAnnotation\DTO\RouteConfigDTO;

/**
 * 优化Swagger 路由注册器
 *
 * 在注册前对路由排序：静态路由优先于变量路由，
 * 避免 FastRoute 报 "static route shadowed by variable route" 错误。
 */
class SwaggerRouteRegister implements RouteRegisterInterface
{
    /**
     * @param array<string, RouteConfigDTO> $routes
     */
    public function register(array $routes): void
    {
        $sorted = $this->sortRoutes($routes);

        foreach ($sorted as $key => $routeConfig) {
            Route::add($routeConfig->method, $routeConfig->path, [$routeConfig->controller, $routeConfig->action])
                ->name($routeConfig->name ?: $key)
                ->middleware($routeConfig->middlewares);
        }
    }

    public function addRoute(string $method, string $path, \Closure $callback, mixed $middlewares = null, ?string $name = null): void
    {
        $route = Route::add(strtoupper($method), $path, $callback)->middleware($middlewares);
        if ($name) {
            $route->name($name);
        }
    }

    public function getUrlByName(string $name): ?string
    {
        return Route::getByName($name)?->url();
    }

    /**
     * 排序路由：静态路由（无 { 路径参数）优先于变量路由
     * 同类型内保持原始顺序
     */
    private function sortRoutes(array $routes): array
    {
        $static = [];
        $variable = [];

        foreach ($routes as $key => $route) {
            if (str_contains($route->path, '{')) {
                $variable[$key] = $route;
            } else {
                $static[$key] = $route;
            }
        }

        return array_merge($static, $variable);
    }
}
