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

use Closure;
use core\io\uuid\Snowflake;
use Webman\Route;

/**
 * webman路由处理
 *
 * @author Mr.April
 * @since  1.0
 */
class RouteOrganizerService
{

    /**
     * 输出路由列表
     *
     * @param string $format
     *
     * @return mixed
     */
    public function getRoutes(string $format = 'normal'): array
    {
        $data = [];
        foreach (Route::getRoutes() as $route) {
            $callback = $route->getCallback();
            if ($callback instanceof Closure) {
                continue;
            }
            $row    = [
                'name'       => $route->getName(),
                'path'       => $route->getPath(),
                'methods'    => $route->getMethods()[0],
                'middleware' => $route->getMiddleware(),
                'file_path'  => $callback[0],
                'action'     => $callback[1],
            ];
            $data[] = $row;
        }
        $methods         = [
            'tree'     => 'buildRouteTree',
            'pid_tree' => 'buildPidTree',
            'normal'   => 'buildSnowflakeTree',
        ];
        $format_function = $methods[$format] ?? 'formatNormal';
        return call_user_func([$this, $format_function], $data);
    }

    /**
     * 路由数据检索器
     *
     * @param array       $routes  原始路由数据集
     * @param string|null $type    精确匹配类型(route/directory)
     * @param string|null $keyword 名称模糊关键词
     *
     * @return array 过滤后的路由列表
     */
    public function search(array $routes, ?string $type = null, ?string $keyword = null): array
    {
        return array_values(array_filter($routes, function ($route) use ($type, $keyword) {
            // 类型匹配检测
            $typeMatch = ($type === null) || ($route['type'] === $type);

            // 名称模糊匹配检测
            $nameMatch = ($keyword === null) ||
                (mb_stripos($route['name'], $keyword) !== false);

            return $typeMatch && $nameMatch;
        }));
    }

    /**
     * 路由数据重构输出children格式
     *
     * @param array $routes
     *
     * @return array
     */
    public function buildRouteTree(array $routes): array
    {
        $tree = [];
        foreach ($routes as $route) {
            $parts   = explode('.', $route['name']);
            $current = &$tree;
            $depth   = 0;

            while ($depth < count($parts)) {
                $isLeaf      = ($depth === count($parts) - 1);
                $currentPart = $parts[$depth];

                // 节点类型判断
                $nodeType = $isLeaf ? 'route' : 'directory';

                // 合并路由数据
                $nodeData = ($isLeaf)
                    ? array_merge($route, ['name' => $currentPart, 'type' => $nodeType])
                    : ['type' => $nodeType, 'name' => $currentPart, 'children' => []];

                // 搜索已存在节点
                $existIndex = array_search($currentPart, array_column($current, 'name'));

                if ($existIndex === false) {
                    $current[]  = $nodeData;
                    $existIndex = array_key_last($current);
                }

                // 指针下钻（仅目录需要）
                if (!$isLeaf) {
                    $current = &$current[$existIndex]['children'];
                }

                $depth++;
            }
        }
        return $tree;
    }

    /**
     * 路由数据重构输出列表格式
     *
     * @param array $routes
     *
     * @return array
     */
    public function buildPidTree(array $routes): array
    {
        $nodes     = [];
        $idCounter = 1;
        $pathMap   = ['root' => ['id' => 0]]; // 根节点锚点

        foreach ($routes as $route) {
            $parts       = explode('.', $route['name']);
            $currentPath = 'root';
            $parentId    = 0;

            foreach ($parts as $part) {
                $nodePath = $currentPath . '.' . $part;

                // 节点已存在检测
                if (!isset($pathMap[$nodePath])) {
                    $isLeaf = ($part === end($parts));

                    $node = [
                            'id'   => $idCounter,
                            'pid'  => $parentId,
                            'name' => $part,
                            'type' => $isLeaf ? 'route' : 'directory',
                        ] + ($isLeaf ? $route : []); // 合并路由元数据

                    $nodes[]            = $node;
                    $pathMap[$nodePath] = [
                        'id'  => $idCounter,
                        'pid' => $parentId,
                    ];
                    $idCounter++;
                }

                // 更新指针
                $parentId    = $pathMap[$nodePath]['id'];
                $currentPath = $nodePath;
            }
        }

        return $nodes;
    }

    public function buildSnowflakeTree(array $routes, int $workerId = 1, int $dataCenterId = 1): array
    {
        $nodes     = [];
        $pathMap   = ['root' => ['id' => Snowflake::generate(), 'level' => 0]]; // 初始化根节点层级

        foreach ($routes as $route) {
            $parts        = explode('.', $route['name']);
            $currentPath  = 'root';
            $parentId     = 0; // 顶级节点的 pid 设为 0
            $currentLevel = 0; // 当前层级计数器

            foreach ($parts as $index => $part) {
                $nodePath = $currentPath . '.' . $part;
                $isLeaf   = ($index === count($parts) - 1);

                // 节点存在性检测
                if (!isset($pathMap[$nodePath])) {
                    $nodeId = Snowflake::generate();
                    $node   = [
                        'id'    => $nodeId,
                        'pid'   => $parentId,
                        'name'  => $part,       // 仅存储当前层级名称
                        'level' => $currentLevel + 1, // 层级=父层级+1
                        'type'  => $isLeaf ? 'route' : 'directory',
                    ];

                    // 合并路由元数据
                    if ($isLeaf) {
                        unset($route['name']); // 移除冗余字段
                        $node = array_merge($node, $route);
                    }

                    $nodes[]            = $node;
                    $pathMap[$nodePath] = [
                        'id'    => $nodeId,
                        'pid'   => $parentId,
                        'level' => $currentLevel + 1,
                    ];
                }

                // 更新指针
                $parentId     = $pathMap[$nodePath]['id'];
                $currentLevel = $pathMap[$nodePath]['level']; // 继承当前层级
                $currentPath  = $nodePath;
            }
        }
        return $nodes;
    }
}
