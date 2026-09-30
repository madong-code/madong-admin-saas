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
namespace app\service\core\metadata;

use ReflectionClass;
use ReflectionMethod;

/**
 * 权限收集服务
 * 用于扫描控制器中的 Permission 注解并生成权限列表
 */
class MetadataCollectorService
{
    /**
     * 收集权限列表
     *
     * @return array
     * @throws \Exception
     */
    public function collect(): array
    {
        $permissions = [];
        
        try {
            // 扫描 adminapi 控制器目录
            $adminApiDir = base_path('app/adminapi/controller');
            $this->scanDirectory($adminApiDir, $permissions, 'adminapi');

            // 扫描 platform 控制器目录
            $platformDir = base_path('app/platform/controller');
            $this->scanDirectory($platformDir, $permissions, 'platform');

            // 扫描 api 控制器目录
            $apiDir = base_path('app/api/controller');
            $this->scanDirectory($apiDir, $permissions, 'api');

            // 扫描 plugin 目录中的控制器
            $pluginDir = base_path('plugin');
            $this->scanDirectory($pluginDir, $permissions, 'plugin');
        } catch (\Exception $e) {
            throw new \Exception('扫描目录时出错: ' . $e->getMessage());
        }
        return $permissions;
    }

    /**
     * 扫描目录中的控制器文件
     *
     * @param string $directory
     * @param array &$permissions
     * @param string $module
     *
     * @throws \Exception
     */
    private function scanDirectory(string $directory, array &$permissions, string $module): void
    {
        try {
            if (!is_dir($directory)) {
                return;
            }
            
            $files = scandir($directory);
            if ($files === false) {
                throw new \Exception('无法读取目录: ' . $directory);
            }
            
            foreach ($files as $file) {
                if ($file === '.' || $file === '..') {
                    continue;
                }
                
                $path = $directory . DIRECTORY_SEPARATOR . $file;
                if (is_dir($path)) {
                    // 跳过 member 和 web 目录，因为其中的控制器可能会导致错误
                    if (str_contains($path, 'member') || str_contains($path, 'web')) {
                        continue;
                    }
                    // 递归扫描子目录
                    try {
                        $this->scanDirectory($path, $permissions, $module);
                    } catch (\Exception $e) {
                        // 忽略扫描子目录时的错误，继续处理其他目录
                        continue;
                    }
                } elseif (str_ends_with($file, 'Controller.php')) {
                    // 扫描控制器文件，使用 try-catch 块捕获所有可能的错误
                    try {
                        $this->scanController($path, $permissions, $module);
                    } catch (\Exception $e) {
                        // 忽略扫描控制器时的错误，继续处理其他控制器
                        continue;
                    }
                }
            }
        } catch (\Exception $e) {
            throw new \Exception('扫描目录时出错: ' . $e->getMessage() . ' (目录: ' . $directory . ')');
        }
    }
    
    /**
     * 扫描控制器文件中的权限注解
     *
     * @param string $filePath
     * @param array &$permissions
     * @param string $module
     */
    private function scanController(string $filePath, array &$permissions, string $module): void
    {
        try {
            // 获取类名
        $relativePath = str_replace(base_path(), '', $filePath);
        $relativePath = ltrim($relativePath, DIRECTORY_SEPARATOR);
        // 确保使用反斜杠作为命名空间分隔符
        $className = str_replace(['/', '\\'], '\\', $relativePath);
        $className = str_replace('.php', '', $className);
        $className = '\\' . $className;
            
            // 检查类是否存在
            if (!class_exists($className)) {
                return;
            }
            
            // 使用反射获取类信息
            $reflectionClass = new ReflectionClass($className);
            
            // 生成路由地址
            $routePath = $this->generateRoutePath($filePath, $module);
            
            // 扫描所有方法
            $methods = $reflectionClass->getMethods(ReflectionMethod::IS_PUBLIC);
            foreach ($methods as $method) {
                // 跳过魔术方法和继承的方法
                $methodName = $method->getName();
                if (str_starts_with($methodName, '__')) {
                    continue;
                }
                // 不需要跳过继承的方法，因为我们需要收集所有带有 Permission 注解的方法
                
                // 初始化权限信息和 swagger 信息
                $permission = null;
                $swaggerInfo = [];
                
                // 尝试获取方法的注解
                try {
                    $attributes = $method->getAttributes();
                    foreach ($attributes as $attribute) {
                        try {
                            $attributeName = $attribute->getName();
                            // 检查是否是 Permission 注解
                            if (str_ends_with($attributeName, 'Permission')) {
                                try {
                                    $args = $attribute->getArguments();
                                    $permission = $this->parsePermission($args);
                                } catch (\Exception $e) {
                                    // 忽略获取权限注解参数时的错误
                                    continue;
                                }
                            }
                            // 检查是否是 swagger 注解
                            if (str_starts_with($attributeName, 'OpenApi\\Attributes\\')) {
                                try {
                                    // 提取 HTTP 方法：OpenApi\Attributes\Get → GET
                                    $httpMethod = strtoupper(substr($attributeName, strlen('OpenApi\\Attributes\\')));
                                    if (in_array($httpMethod, ['GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'HEAD', 'OPTIONS'])) {
                                        $swaggerInfo['httpMethod'] = $httpMethod;
                                    }

                                    $args = $attribute->getArguments();
                                    if (isset($args['tags'])) {
                                        // 处理 tags，确保是字符串数组
                                        $tags = $args['tags'];
                                        if (is_array($tags)) {
                                            $swaggerInfo['tags'] = array_map(function($tag) {
                                                // 如果是对象，尝试获取 name 属性
                                                if (is_object($tag) && isset($tag->name)) {
                                                    return $tag->name;
                                                }
                                                // 如果是字符串，直接使用
                                                if (is_string($tag)) {
                                                    return $tag;
                                                }
                                                // 其他情况，转换为字符串
                                                return (string) $tag;
                                            }, $tags);
                                        } elseif (is_string($tags)) {
                                            $swaggerInfo['tags'] = [$tags];
                                        }
                                    }
                                    if (isset($args['summary'])) {
                                        $swaggerInfo['summary'] = $args['summary'];
                                    }
                                } catch (\Exception $e) {
                                    // 忽略获取 swagger 注解参数时的错误
                                    continue;
                                }
                            }
                        } catch (\Exception $e) {
                            // 忽略获取注解信息时的错误
                            continue;
                        }
                    }
                } catch (\Exception $e) {
                    // 忽略获取方法注解时的错误
                    continue;
                }
                
                // 如果有 Permission 注解，添加控制器和方法信息，并合并 swagger 信息
                if ($permission) {
                    // 添加控制器和方法信息
                    $permission['controller'] = $reflectionClass->getShortName();
                    $permission['method'] = $method->getName();
                    $permission['module'] = $module;
                    $permission['route'] = $this->generateMethodRoute($routePath, $method->getName());
                    
                    // 合并 swagger 信息
                    if (!empty($swaggerInfo)) {
                        $permission = array_merge($permission, $swaggerInfo);
                    }
                    
                    $permissions[] = $permission;
                }
            }
        } catch (\Exception $e) {
            // 忽略扫描控制器时的错误，继续处理其他控制器
            return;
        }
    }
    
    /**
     * 解析权限注解参数
     *
     * @param array $args
     * @return array|null
     */
    private function parsePermission(array $args): ?array
    {
        $permission = [];
        
        // 处理直接传递字符串的情况
        if (isset($args[0]) && is_string($args[0])) {
            $permission['code'] = $args[0];
        } elseif (isset($args['code'])) {
            $permission['code'] = $args['code'];
        } else {
            return null;
        }
        
        // 处理描述
        if (isset($args['description'])) {
            $permission['description'] = $args['description'];
        }
        
        // 处理 tags，确保是字符串数组
        if (isset($args['tags'])) {
            $tags = $args['tags'];
            if (is_array($tags)) {
                $permission['tags'] = array_map(function($tag) {
                    // 如果是对象，尝试获取 name 属性
                    if (is_object($tag) && isset($tag->name)) {
                        return $tag->name;
                    }
                    // 如果是字符串，直接使用
                    if (is_string($tag)) {
                        return $tag;
                    }
                    // 其他情况，转换为字符串
                    return (string) $tag;
                }, $tags);
            } elseif (is_string($tags)) {
                $permission['tags'] = [$tags];
            }
        }
        
        // 处理 summary
        if (isset($args['summary'])) {
            $permission['summary'] = $args['summary'];
        }
        
        return $permission;
    }

    /**
     * 生成控制器的路由路径
     *
     * @param string $filePath
     * @param string $module
     *
     * @return string
     * @throws \Exception
     */
    private function generateRoutePath(string $filePath, string $module): string
    {
        try {
            // 移除基础路径
            $relativePath = str_replace(base_path(), '', $filePath);
            $relativePath = ltrim($relativePath, DIRECTORY_SEPARATOR);
            
            // 标准化分隔符
            $relativePath = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relativePath);
            
            // 构建控制器目录路径
            $controllerDir = 'app' . DIRECTORY_SEPARATOR . $module . DIRECTORY_SEPARATOR . 'controller' . DIRECTORY_SEPARATOR;
            
            // 检查路径是否包含控制器目录
            if (str_starts_with($relativePath, $controllerDir)) {
                // 提取控制器目录之后的部分
                $routePath = substr($relativePath, strlen($controllerDir));
                // 移除 .php 后缀
                $routePath = str_replace('.php', '', $routePath);
            } else {
                // 如果没有匹配到，返回默认路径
                return '/' . $module;
            }
            
            // 移除 Controller 后缀
            $routePath = str_replace('Controller', '', $routePath);
            
            // 将目录分隔符转换为斜杠
            $routePath = str_replace(DIRECTORY_SEPARATOR, '/', $routePath);
            
            // 转换为小写并添加连字符
            $routePath = strtolower(preg_replace('/([a-z])([A-Z])/', '$1-$2', $routePath));
            
            // 添加模块前缀
            if ($module === 'adminapi') {
                return '/adminapi/' . ltrim($routePath, '/');
            } elseif ($module === 'api') {
                return '/api/' . ltrim($routePath, '/');
            } else {
                return '/' . ltrim($routePath, '/');
            }
        } catch (\Exception $e) {
            throw new \Exception('生成路由路径时出错: ' . $e->getMessage());
        }
    }
    
    /**
     * 生成方法的路由路径
     *
     * @param string $controllerRoute
     * @param string $methodName
     * @return string
     */
    private function generateMethodRoute(string $controllerRoute, string $methodName): string
    {
        // 转换方法名为路由格式
        $methodRoute = strtolower(preg_replace('/([a-z])([A-Z])/', '$1-$2', $methodName));
        
        // 移除常见的 HTTP 方法前缀
        $methodRoute = preg_replace('/^(get|post|put|delete|patch|options)_/', '', $methodRoute);
        
        // 组合路由路径
        return rtrim($controllerRoute, '/') . '/' . $methodRoute;
    }

    /**
     * 导出权限列表为 JSON 文件
     *
     * @param string $outputPath
     *
     * @return bool
     * @throws \Exception
     */
    public function exportToJson(string $outputPath): bool
    {
        $permissions = $this->collect();
        $json = json_encode($permissions, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        return file_put_contents($outputPath, $json) !== false;
    }
}
