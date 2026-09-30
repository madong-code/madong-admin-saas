<?php
declare(strict_types=1);

/**
 *+------------------
 * madong
 *+------------------
 * Copyright (c) https://gitee.com/motion-code  All rights reserved.
 *+------------------
 * Official Website: http://www.madong.tech
 */

namespace app\service\core\enum;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use ReflectionException;

/**
 * 枚举服务类
 *
 * @author Mr.April
 * @since  1.0
 */
final class EnumService
{
    /**
     * 缓存的插件名称列表
     */
    private array $pluginNames = [];

    /**
     * 获取所有插件名称
     *
     * @return array
     */
    private function getPluginNames(): array
    {
        if (!empty($this->pluginNames)) {
            return $this->pluginNames;
        }

        $pluginDir = base_path('plugin');
        if (!is_dir($pluginDir)) {
            $this->pluginNames = [];
            return $this->pluginNames;
        }

        $this->pluginNames = [];
        $dirs = scandir($pluginDir);
        foreach ($dirs as $dir) {
            if ($dir !== '.' && $dir !== '..' && is_dir($pluginDir . DIRECTORY_SEPARATOR . $dir)) {
                $this->pluginNames[] = $dir;
            }
        }

        return $this->pluginNames;
    }

    /**
     * 获取所有枚举目录（递归扫描）
     * '支持扫描 app/enum 和 plugin//app/enum 目录
     * @return array
     */
    public function getEnumDirectories(): array
    {
        $directories = [];

        // 扫描 app/enum 目录
        $appEnumDir = app_path('enum');
        if (is_dir($appEnumDir)) {
            $directories = array_merge($directories, $this->scanDirectory($appEnumDir));
        }

        // 扫描插件目录 plugin/*/app/enum
        $pluginDir = base_path('plugin');
        if (is_dir($pluginDir)) {
            foreach ($this->getPluginNames() as $pluginName) {
                $pluginEnumDir = $pluginDir . DIRECTORY_SEPARATOR . $pluginName . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'enum';
                if (is_dir($pluginEnumDir)) {
                    $directories = array_merge($directories, $this->scanDirectory($pluginEnumDir, $pluginName));
                }
            }
        }

        return $directories;
    }

    /**
     * 扫描指定目录并返回目录映射
     *
     * @param string $baseDir 基础目录
     * @param string $pluginName 插件名称（可选）
     * @return array
     */
    private function scanDirectory(string $baseDir, string $pluginName = ''): array
    {
        $directories = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($baseDir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $file) {
            if ($file->isDir() && $file->getFilename() !== '.' && $file->getFilename() !== '..') {
                $relativePath = str_replace($baseDir . DIRECTORY_SEPARATOR, '', $file->getPathname());
                $relativePath = str_replace(DIRECTORY_SEPARATOR, '.', $relativePath);
                // 如果是插件，则分类以插件名开头
                $category = $pluginName ? ($pluginName . '.' . $relativePath) : $relativePath;
                $directories[$category] = $file->getPathname();
            }
        }

        return $directories;
    }

    /**
     * 扫描所有枚举类（支持多级目录）
     *
     * @return array
     */
    public function scanEnums(): array
    {
        $result = [];
        $directories = $this->getEnumDirectories();

        foreach ($directories as $category => $directory) {
            if (!is_dir($directory)) {
                continue;
            }

            $files = scandir($directory);
            foreach ($files as $file) {
                if ($file === '.' || $file === '..' || !str_ends_with($file, '.php')) {
                    continue;
                }

                $className = pathinfo($file, PATHINFO_FILENAME);
                $namespace = $this->buildNamespace($category, $className);

                if ($this->isEnumClass($namespace)) {
                    $result[] = [
                        'category' => $category,
                        'namespace' => $namespace,
                        'name' => $className,
                        'code' => strtolower($category . '.' . $className),
                    ];
                }
            }
        }

        return $result;
    }

    /**
     * 根据分类路径构建命名空间
     *
     * @param string $category 分类路径（如：system 或 official）
     * @param string $className 类名
     * @return string
     */
    private function buildNamespace(string $category, string $className): string
    {
        $parts = explode('.', $category);
        $firstPart = $parts[0];

        // 判断第一部分是否为插件名
        if (in_array($firstPart, $this->getPluginNames())) {
            // 插件枚举：official → plugin\official\app\enum\PermissionType
            $subPath = count($parts) > 1 ? implode('\\', array_slice($parts, 1)) : '';
            $namespace = "plugin\\{$firstPart}\\app\\enum";
            if ($subPath) {
                $namespace .= "\\{$subPath}";
            }
            $namespace .= "\\{$className}";
        } else {
            // 系统枚举：system → app\enum\system\PermissionType
            $subPath = str_replace('.', '\\', $category);
            $namespace = "app\\enum\\{$subPath}\\{$className}";
        }

        return $namespace;
    }

    /**
     * 根据枚举code获取枚举数据（支持多级目录）
     *
     * @param string $enumCode 枚举代码，格式如：system.permissiontype 或 official.permissiontype
     * @return array
     */
    public function getEnumByCode(string $enumCode): array
    {
        $parts = explode('.', $enumCode);
        if (count($parts) < 2) {
            return [];
        }

        $enumName = array_pop($parts);
        $category = implode('.', $parts);

        $namespace = $this->buildNamespace($category, $enumName);

        if (!$this->isEnumClass($namespace)) {
            return [];
        }

        return $this->getEnumOptions($namespace);
    }

    /**
     * 获取枚举的选项数据
     *
     * @param string $enumClass 枚举类名
     * @return array
     */
    private function getEnumOptions(string $enumClass): array
    {
        try {
            $reflectionClass = new ReflectionClass($enumClass);
            $cases = $reflectionClass->getConstants();
            $options = [];

            // 预定义颜色数组，用于随机分配
            $defaultColors = ['#1890ff', '#52c41a', '#faad14', '#f5222d', '#722ed1', '#fa541c', '#13c2c2', '#eb2f96'];

            foreach ($cases as $caseName => $case) {
                $option = [
                    'label' => method_exists($case, 'label') ? $case->label() : $caseName,
                    'value' => $case->value ?? $caseName,
                ];

                // 添加颜色信息 - 优先使用枚举的color方法，否则使用随机颜色
                if (method_exists($case, 'color')) {
                    $option['color'] = $case->color();
                } else {
                    // 使用随机颜色，确保相同caseName总是得到相同颜色
                    $colorIndex = crc32($caseName) % count($defaultColors);
                    $option['color'] = $defaultColors[$colorIndex];
                }

                // 添加其他扩展信息 - 总是设置ext字段，没有方法时返回空数组
                $option['ext'] = method_exists($case, 'ext') ? $case->ext() : [];

                $options[] = $option;
            }

            return $options;
        } catch (ReflectionException $e) {
            return [];
        }
    }

    /**
     * 检查是否为枚举类
     *
     * @param string $className 类名
     * @return bool
     */
    private function isEnumClass(string $className): bool
    {
        try {
            $reflectionClass = new ReflectionClass($className);
            return $reflectionClass->isEnum();
        } catch (ReflectionException $e) {
            return false;
        }
    }

    /**
     * 获取所有枚举的完整列表（支持多级目录，一维结构）
     *
     * @return array
     */
    public function getAllEnums(): array
    {
        $result = [];
        $directories = $this->getEnumDirectories();
        foreach ($directories as $category => $directory) {
            if (!is_dir($directory)) {
                continue;
            }

            $files = scandir($directory);
            foreach ($files as $file) {
                if ($file === '.' || $file === '..' || !str_ends_with($file, '.php')) {
                    continue;
                }

                $className = pathinfo($file, PATHINFO_FILENAME);
                $namespace = $this->buildNamespace($category, $className);

                if ($this->isEnumClass($namespace)) {
                    $options = $this->getEnumOptions($namespace);
                    $result[] = [
                        'name' => $className,
                        'namespace' => $namespace,
                        'category' => $category,
                        'category_label' => $this->getCategoryLabel($category),
                        'code' => strtolower($category . '.' . $className),
                        'options' => $options,
                        'count' => count($options),
                    ];
                }
            }
        }

        return $result;
    }

    /**
     * 获取分类标签（支持多级目录）
     *
     * @param string $category 分类
     * @return string
     */
    private function getCategoryLabel(string $category): string
    {
        $categoryEnum = \app\enum\common\CategoryEnum::fromDirectory($category);
        if ($categoryEnum !== null) {
            return $categoryEnum->label();
        }

        $labels = [
            'common' => '通用枚举',
            'system' => '系统枚举',
        ];

        return $labels[$category] ?? $this->generateCategoryLabel($category);
    }

    /**
     * 生成分类标签（用于未映射的分类）
     *
     * @param string $category 分类路径
     * @return string
     */
    private function generateCategoryLabel(string $category): string
    {
        $parts = explode('.', $category);
        $lastPart = end($parts);
        return ucfirst($lastPart) . '枚举';
    }

    /**
     * 获取所有可用的枚举代码列表
     *
     * @return array
     */
    public function getAvailableEnumCodes(): array
    {
        $enums = $this->scanEnums();
        return array_column($enums, 'code');
    }

    /**
     * 检查枚举代码是否有效
     *
     * @param string $enumCode 枚举代码
     * @return bool
     */
    public function isValidEnumCode(string $enumCode): bool
    {
        $parts = explode('.', $enumCode);
        if (count($parts) < 2) {
            return false;
        }

        $enumName = array_pop($parts);
        $category = implode('.', $parts);
        $namespace = $this->buildNamespace($category, $enumName);

        return $this->isEnumClass($namespace);
    }

    /**
     * 批量获取多个枚举数据
     *
     * @param array $enumCodes 枚举代码数组
     * @return array
     */
    public function getBatchEnums(array $enumCodes): array
    {
        $result = [];
        foreach ($enumCodes as $enumCode) {
            if ($this->isValidEnumCode($enumCode)) {
                $result[$enumCode] = $this->getEnumByCode($enumCode);
            }
        }
        return $result;
    }

    /**
     * 获取枚举的详细信息
     *
     * @param string $enumCode 枚举代码
     * @return array
     */
    public function getEnumInfo(string $enumCode): array
    {
        if (!$this->isValidEnumCode($enumCode)) {
            return [];
        }

        $parts = explode('.', $enumCode);
        $enumName = array_pop($parts);
        $category = implode('.', $parts);
        $namespace = $this->buildNamespace($category, $enumName);

        try {
            $reflectionClass = new ReflectionClass($namespace);
            $cases = $reflectionClass->getConstants();

            return [
                'code' => $enumCode,
                'name' => $enumName,
                'category' => $category,
                'namespace' => $namespace,
                'case_count' => count($cases),
                'cases' => array_keys($cases),
            ];
        } catch (ReflectionException $e) {
            return [];
        }
    }

    /**
     * 获取枚举列表-支持搜索和分类筛选，不分页，一维结构
     *
     * @param string $search 搜索关键词
     * @param string $category 分类筛选
     * @return array
     */
    public function getEnumsList(string $search = '', string $category = ''): array
    {
        $allEnums = $this->getAllEnums();
        $filteredEnums = [];

        foreach ($allEnums as $enum) {
            // 分类筛选
            if ($category && $enum['category'] !== $category) {
                continue;
            }

            // 搜索筛选
            if ($search && !str_contains(strtolower($enum['name']), strtolower($search))) {
                continue;
            }

            $filteredEnums[] = $enum;
        }

        return $filteredEnums;
    }

    /**
     * 获取枚举总数（用于分页计数）
     *
     * @param string $search 搜索关键词
     * @param string $category 分类筛选
     * @return int
     */
    public function getEnumsCount(string $search = '', string $category = ''): int
    {
        return count($this->getEnumsList($search, $category));
    }

    /**
     * 分页获取枚举列表（以枚举类为计数单位，一维结构）
     *
     * @param int $page 页码
     * @param int $limit 每页数量
     * @param string $search 搜索关键词
     * @param string $category 分类筛选
     * @return array
     */
    public function getEnumsWithPagination(int $page = 1, int $limit = 10, string $search = '', string $category = ''): array
    {
        $filteredEnums = $this->getEnumsList($search, $category);

        // 分页处理
        $total = count($filteredEnums);
        $offset = ($page - 1) * $limit;
        $pagedEnums = array_slice($filteredEnums, $offset, $limit);

        return [
            'items' => $pagedEnums,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'total_page' => ceil($total / $limit),
        ];
    }

    /**
     * 根据分类获取枚举列表
     *
     * @param string $category 分类
     * @return array
     */
    public function getEnumsByCategory(string $category): array
    {
        return $this->getEnumsList('', $category);
    }

    /**
     * 搜索枚举
     *
     * @param string $keyword 关键词
     * @param int $limit 限制数量
     * @return array
     */
    public function searchEnums(string $keyword, int $limit = 20): array
    {
        $allEnums = $this->getAllEnums();
        $result = [];

        foreach ($allEnums as $enum) {
            if (str_contains(strtolower($enum['name']), strtolower($keyword))) {
                $result[] = $enum;

                if (count($result) >= $limit) {
                    break;
                }
            }
        }

        return $result;
    }

    /**
     * 获取枚举统计信息
     *
     * @return array
     */
    public function getEnumStatistics(): array
    {
        $allEnums = $this->getAllEnums();
        $statistics = [
            'total_enums' => count($allEnums),
            'total_options' => 0,
            'categories' => [],
        ];

        $categoryStats = [];
        foreach ($allEnums as $enum) {
            $statistics['total_options'] += $enum['count'];

            if (!isset($categoryStats[$enum['category']])) {
                $categoryStats[$enum['category']] = [
                    'category' => $enum['category'],
                    'label' => $enum['categoryLabel'],
                    'enum_count' => 0,
                    'option_count' => 0,
                ];
            }
            $categoryStats[$enum['category']]['enum_count']++;
            $categoryStats[$enum['category']]['option_count'] += $enum['count'];
        }

        $statistics['categories'] = array_values($categoryStats);
        $statistics['total_categories'] = count($categoryStats);

        return $statistics;
    }

    /**
     * 获取所有分类列表
     *
     * @return array
     */
    public function getCategoryList(): array
    {
        $allEnums = $this->getAllEnums();
        $categories = [];
        $seenCategories = [];

        foreach ($allEnums as $enum) {
            if (!in_array($enum['category'], $seenCategories)) {
                $seenCategories[] = $enum['category'];
                $categories[] = [
                    'value' => $enum['category'],
                    'label' => $enum['categoryLabel'],
                ];
            }
        }

        return $categories;
    }

    /**
     * 检查枚举是否存在
     *
     * @param string $enumCode 枚举代码
     * @return bool
     */
    public function enumExists(string $enumCode): bool
    {
        return $this->isValidEnumCode($enumCode);
    }

    /**
     * 获取枚举的选项数量
     *
     * @param string $enumCode 枚举代码
     * @return int
     */
    public function getEnumOptionCount(string $enumCode): int
    {
        $options = $this->getEnumByCode($enumCode);
        return count($options);
    }

    /**
     * 批量检查枚举代码有效性
     *
     * @param array $enumCodes 枚举代码数组
     * @return array
     */
    public function validateEnumCodes(array $enumCodes): array
    {
        $result = [
            'valid' => [],
            'invalid' => [],
        ];

        foreach ($enumCodes as $enumCode) {
            if ($this->isValidEnumCode($enumCode)) {
                $result['valid'][] = $enumCode;
            } else {
                $result['invalid'][] = $enumCode;
            }
        }

        return $result;
    }

    /**
     * 获取枚举的默认值（第一个选项的值）
     *
     * @param string $enumCode 枚举代码
     * @return mixed
     */
    public function getEnumDefaultValue(string $enumCode): mixed
    {
        $options = $this->getEnumByCode($enumCode);
        if (empty($options)) {
            return null;
        }

        return $options[0]['value'] ?? null;
    }

    /**
     * 根据值获取枚举标签
     *
     * @param string $enumCode 枚举代码
     * @param mixed $value 枚举值
     * @return string|null
     */
    public function getLabelByValue(string $enumCode, mixed $value): ?string
    {
        $options = $this->getEnumByCode($enumCode);

        foreach ($options as $option) {
            if ($option['value'] == $value) {
                return $option['label'];
            }
        }

        return null;
    }

    /**
     * 根据标签获取枚举值
     *
     * @param string $enumCode 枚举代码
     * @param string $label 枚举标签
     * @return mixed
     */
    public function getValueByLabel(string $enumCode, string $label): mixed
    {
        $options = $this->getEnumByCode($enumCode);

        foreach ($options as $option) {
            if ($option['label'] === $label) {
                return $option['value'];
            }
        }

        return null;
    }

    /**
     * 获取枚举的颜色信息
     *
     * @param string $enumCode 枚举代码
     * @param mixed $value 枚举值
     * @return string|null
     */
    public function getColorByValue(string $enumCode, mixed $value): ?string
    {
        $options = $this->getEnumByCode($enumCode);

        foreach ($options as $option) {
            if ($option['value'] == $value) {
                return $option['color'] ?? null;
            }
        }

        return null;
    }

    /**
     * 重新扫描枚举
     *
     * @return array
     */
    public function rescanEnums(): array
    {
        // 这里可以添加缓存清除逻辑
        return $this->getAllEnums();
    }
}
