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
namespace core\business\generator\utils;

/**
 * 路径解析器
 * 负责处理文件路径的解析和生成
 */
class PathResolver
{
    /**
     * 生成文件路径
     * @param string $basePath 基础路径
     * @param string $moduleName 模块名称
     * @param string $className 类名
     * @param string $fileType 文件类型
     * @param string $extension 文件扩展名
     * @param bool $isPlugin 是否是插件模式
     * @return string 文件路径
     */
    public function generatePath(string $basePath, string $moduleName, string $className, string $fileType, string $extension = 'php', bool $isPlugin = false): string
    {
        // 前端文件类型
        $frontendFileTypes = ['api', 'api_model', 'view', 'view_schema', 'lang'];
        
        // 根据文件类型处理模块名格式
        $processedModuleName = $moduleName;
        if (in_array($fileType, $frontendFileTypes)) {
            // 前端使用连字符格式
            $processedModuleName = str_replace('_', '-', $moduleName);
        } else {
            // 后端使用下划线格式
            $processedModuleName = str_replace('-', '_', $moduleName);
        }
        
        // 确保 className 是大驼峰格式（处理下划线分隔的情况）
        $className = str_replace(' ', '', ucwords(str_replace('_', ' ', $className)));
        
        // 插件模式下，service 路径不包含 admin 层级
        $servicePath = $isPlugin ? 'service/' : 'service/admin/';
        
        $pathMap = [
            // 后端文件路径映射
            'controller' => 'controller/' . ($processedModuleName ? $processedModuleName . '/' : '') . $className . 'Controller.' . $extension,
            'model' => 'model/' . ($processedModuleName ? $processedModuleName . '/' : '') . $className . '.' . $extension,
            'service' => $servicePath . ($processedModuleName ? $processedModuleName . '/' : '') . $className . 'Service.' . $extension,
            'dao' => 'dao/' . ($processedModuleName ? $processedModuleName . '/' : '') . $className . 'Dao.' . $extension,
            'validate' => 'validate/' . ($processedModuleName ? $processedModuleName . '/' : '') . $className . 'Validate.' . $extension,
            'response' => 'schema/response/' . ($processedModuleName ? $processedModuleName . '/' : '') . $className . 'Response.' . $extension,
            'request_form' => 'schema/request/' . ($processedModuleName ? $processedModuleName . '/' : '') . $className . 'FormRequest.' . $extension,
            'request_query' => 'schema/request/' . ($processedModuleName ? $processedModuleName . '/' : '') . $className . 'QueryRequest.' . $extension,
            'migration' => 'migrations/' . ($processedModuleName ? $processedModuleName . '/' : '') . 'create_' . $processedModuleName . '_table.' . $extension,
            'route' => 'route/' . ($processedModuleName ? $processedModuleName . '/' : '') . $processedModuleName . '.' . $extension,

            // 前端文件路径映射
            'api' => 'api/' . ($processedModuleName ? $processedModuleName . '/' : '') . 'index.ts',
            'api_model' => 'api/' . ($processedModuleName ? $processedModuleName . '/' : '') . 'types.ts',
            'view' => 'views/' . ($processedModuleName ? $processedModuleName . '/' : '') . 'index.vue',
            'view_schema' => 'views/' . ($processedModuleName ? $processedModuleName . '/' : '') . 'schemas/index.tsx',
            'lang' => 'lang/zh-cn/' . $processedModuleName . '.json',
        ];
        
        $relativePath = $pathMap[$fileType] ?? $fileType . '/' . $className . '.' . $extension;
        return $basePath . DS . $relativePath;
    }

    /**
     * 确保路径存在
     * @param string $path 路径
     * @return void
     */
    public function ensurePathExists(string $path): void
    {
        $dirPath = dirname($path);
        
        if (!is_dir($dirPath)) {
            mkdir($dirPath, 0777, true);
        }
    }

    /**
     * 获取相对路径
     * @param string $fullPath 完整路径
     * @param string $basePath 基础路径
     * @return string 相对路径
     */
    public function getRelativePath(string $fullPath, string $basePath): string
    {
        return str_replace($basePath . DS, '', $fullPath);
    }
}
