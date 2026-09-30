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

namespace core\communication\mcp\tool\dev;

use core\communication\mcp\attribute\McpTool;
use core\communication\mcp\security\McpUser;
use Webman\Route;
use Webman\Route\Route as RouteObject;

/**
 * route_list：已注册路由清单（联调查 API 端点）
 */
final class RouteListTool
{
    public function __construct(
        private readonly ?McpUser $user = null,
    ) {
    }

    #[McpTool(
        name: 'route_list',
        title: 'Route List',
        description: '列出 madong 后端已注册的 HTTP 路由（方法/路径/回调/路由名），支持按方法与关键词过滤，用于联调时确认端点是否存在。',
        inputSchema: [
            'type' => 'object',
            'properties' => [
                'keyword' => [
                    'type' => 'string',
                    'description' => '按路径/回调名过滤（不区分大小写，空=全部）',
                ],
                'method' => [
                    'type' => 'string',
                    'enum' => ['', 'GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'HEAD', 'OPTIONS'],
                    'description' => '按 HTTP 方法过滤，空=全部',
                ],
                'limit' => [
                    'type' => 'integer',
                    'default' => 200,
                    'maximum' => 1000,
                    'description' => '返回条数上限',
                ],
            ],
        ],
        permission: false,
    )]
    public function routeList(string $keyword = '', string $method = '', int $limit = 200): array
    {
        $limit   = min(1000, max(1, $limit));
        $keyword = trim($keyword);
        $method  = strtoupper(trim($method));

        $routes = [];
        foreach (Route::getRoutes() as $route) {
            $path = (string) $route->getPath();
            if ($path === '') {
                continue;
            }
            $callback = $this->normalizeCallback($route->getCallback());
            if ($keyword !== ''
                && stripos($path, $keyword) === false
                && stripos($callback, $keyword) === false) {
                continue;
            }
            $methods = $route->getMethods() ?: ['ANY'];
            if ($method !== '' && !in_array($method, $methods, true)) {
                continue;
            }
            $routes[] = [
                'methods'     => $methods,
                'path'        => $path,
                'name'        => $route->getName() ?? '',
                'callback'    => $callback,
                'middlewares' => $this->normalizeMiddlewares($route->getMiddleware()),
            ];
        }

        $total = count($routes);
        return ['total' => $total, 'routes' => array_slice($routes, 0, $limit)];
    }

    private function normalizeCallback(mixed $callback): string
    {
        if (is_array($callback)) {
            return (is_object($callback[0]) ? $callback[0]::class : (string) $callback[0])
                . '@' . (string) $callback[1];
        }
        if ($callback instanceof \Closure) {
            return 'Closure';
        }
        return (string) $callback;
    }

    /**
     * 将中间件数组（可能含对象/闭包）标准化为类名列表
     */
    private function normalizeMiddlewares(array $middlewares): array
    {
        $result = [];
        foreach ($middlewares as $m) {
            if (is_string($m)) {
                $result[] = $m;
            } elseif (is_object($m)) {
                $result[] = $m instanceof \Closure ? 'Closure' : get_class($m);
            } else {
                $result[] = get_debug_type($m);
            }
        }
        return $result;
    }
}
