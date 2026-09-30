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

namespace app\service\admin\system\role;

use app\service\core\metadata\MetadataCollectorService;
use core\foundation\base\BaseService;
use support\Container;

/**
 * 规则扫描服务
 * 使用 MetadataCollectorService 扫描控制器，提供缓存机制
 */
class RuleService extends BaseService
{
    /**
     * 缓存键
     */
    const CACHE_KEY = 'rule_data';
    const CACHE_TTL = 300; // 缓存5分钟

    /**
     * 获取扫描的权限列表（带缓存）
     *
     * @return array
     * @throws \Exception
     */
    public function getPermissions(): array
    {
        return $this->cacheDriver()->remember(
            self::CACHE_KEY,
            function () {
                $collector = Container::get(MetadataCollectorService::class);
                return $collector->collect();
            },
            self::CACHE_TTL
        );
    }

    /**
     * 获取分类树（基于 tags）
     *
     * @return array
     * @throws \Exception
     */
    public function getCategories(): array
    {
        $permissions = $this->getPermissions();
        $categories  = [];
        $categoryMap = [];

        foreach ($permissions as $permission) {
            // 确保 tags 是字符串数组
            $tags = $permission['tags'] ?? ['未分类'];
            if (!is_array($tags)) {
                $tags = [$tags];
            }
            // 确保数组中的每个元素都是字符串
            $tags = array_map(function($tag) {
                if (is_string($tag)) {
                    return $tag;
                }
                if (is_object($tag) && isset($tag->name)) {
                    return $tag->name;
                }
                return (string) $tag;
            }, $tags);

            foreach ($tags as $tag) {
                if (!isset($categoryMap[$tag])) {
                    $categoryId        = md5($tag);
                    $categoryMap[$tag] = $categoryId;
                    $categories[]      = [
                        'id'       => $categoryId,
                        'pid'      => 0,
                        'name'     => $tag,
                        'value'    => $categoryId,
                        'label'    => $tag,
                        'children' => [],
                    ];
                }
            }
        }

        return $categories;
    }

    /**
     * 根据分类获取接口列表
     *
     * @param string|null $categoryId
     * @param string|null $keyword
     *
     * @return array
     * @throws \Exception
     */
    public function getRoutesByCategory(?string $categoryId = null, ?string $keyword = null): array
    {
        $permissions = $this->getPermissions();
        $routes      = [];

        foreach ($permissions as $permission) {
            // 确保 tags 是字符串数组
            $tags = $permission['tags'] ?? ['未分类'];
            if (!is_array($tags)) {
                $tags = [$tags];
            }
            // 确保数组中的每个元素都是字符串
            $tags = array_map(function($tag) {
                if (is_string($tag)) {
                    return $tag;
                }
                if (is_object($tag) && isset($tag->name)) {
                    return $tag->name;
                }
                return (string) $tag;
            }, $tags);

            // 过滤分类
            if ($categoryId !== null) {
                $categoryIds = array_map(fn($tag) => md5($tag), $tags);
                if (!in_array($categoryId, $categoryIds)) {
                    continue;
                }
            }

            // 过滤关键词
            if ($keyword !== null && !empty($keyword)) {
                // 构建搜索文本，只使用字符串类型的字段
                $searchParts = [];

                // 添加 tags
                $searchParts[] = implode(' ', $tags);

                // 添加其他字段（确保是字符串）
                $fields = ['summary', 'description', 'controller', 'method', 'code', 'route'];
                foreach ($fields as $field) {
                    if (isset($permission[$field]) && is_string($permission[$field])) {
                        $searchParts[] = $permission[$field];
                    }
                }

                $searchText = implode(' ', $searchParts);

                if (stripos($searchText, $keyword) === false) {
                    continue;
                }
            }

            $routes[] = [
                'id'          => md5($permission['route'] . '_' . $permission['method']),
                'name'        => $permission['summary'] ?? $permission['description'] ?? $permission['code'],
                'method'      => $permission['httpMethod'] ?? $this->extractHttpMethod($permission['method']),
                'path'        => $permission['route'],
                'code'        => $permission['code'],
                'controller'  => $permission['controller'],
                'action'      => $permission['method'],
                'description' => $permission['description'] ?? '',
                'tags'        => $tags,
            ];
        }

        return $routes;
    }

    /**
     * 从方法名中提取 HTTP 方法
     *
     * @param string $methodName
     *
     * @return string
     */
    private function extractHttpMethod(string $methodName): string
    {
        $prefixes    = ['get', 'post', 'put', 'delete', 'patch'];
        $lowerMethod = strtolower($methodName);

        foreach ($prefixes as $prefix) {
            if (str_starts_with($lowerMethod, $prefix)) {
                return strtoupper($prefix);
            }
        }

        return 'GET';
    }

    /**
     * 清除缓存
     *
     * @return void
     */
    public function clearCache(): void
    {
        $this->cacheDriver()->delete(self::CACHE_KEY);
    }

    /**
     * 手动触发扫描并刷新缓存
     *
     * @return array
     * @throws \Exception
     */
    public function refresh(): array
    {
        $this->clearCache();
        return $this->getPermissions();
    }
}
