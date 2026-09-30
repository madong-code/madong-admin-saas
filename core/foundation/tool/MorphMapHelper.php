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
 * Official Website: https://madong.tech
 */

namespace core\foundation\tool;

use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * 多态映射辅助类
 *
 * 提供便捷的多态映射操作方法
 *
 * @author Mr.April
 * @since  1.0
 */
class MorphMapHelper
{
    /**
     * 获取所有已注册的多态映射
     *
     * @return array
     */
    public static function getAllMaps(): array
    {
        return Relation::morphMap();
    }

    /**
     * 获取指定别名对应的模型类
     *
     * @param string $alias
     * @return string|null
     */
    public static function getModelClass(string $alias): ?string
    {
        return Relation::getMorphedModel($alias);
    }

    /**
     * 获取指定模型类对应的别名
     *
     * @param string $modelClass
     * @return string|null
     */
    public static function getModelAlias(string $modelClass): ?string
    {
        $maps = self::getAllMaps();
        $flipped = array_flip($maps);
        return $flipped[$modelClass] ?? null;
    }

    /**
     * 检查别名是否已注册
     *
     * @param string $alias
     * @return bool
     */
    public static function hasAlias(string $alias): bool
    {
        return array_key_exists($alias, self::getAllMaps());
    }

    /**
     * 检查模型类是否已注册
     *
     * @param string $modelClass
     * @return bool
     */
    public static function hasModelClass(string $modelClass): bool
    {
        return in_array($modelClass, self::getAllMaps());
    }

    /**
     * 格式化输出映射信息（用于调试）
     *
     * @return string
     */
    public static function debugInfo(): string
    {
        $maps = self::getAllMaps();

        if (empty($maps)) {
            return "No morph maps registered.\n";
        }

        $output = "Morph Maps (Total: " . count($maps) . "):\n";
        $output .= str_repeat('-', 80) . "\n";

        foreach ($maps as $alias => $modelClass) {
            $exists = class_exists($modelClass);
            $status = $exists ? '✓' : '✗';
            $output .= sprintf(
                "  %-20s => %-60s %s\n",
                $alias,
                $modelClass,
                $status
            );
        }

        $output .= str_repeat('-', 80) . "\n";
        $output .= "Legend: ✓ = class exists, ✗ = class not found\n";

        return $output;
    }

    /**
     * 根据模型类自动生成别名（用于开发时生成配置）
     *
     * @param string $modelClass
     * @return string
     */
    public static function generateAlias(string $modelClass): string
    {
        // 移除命名空间前缀
        $className = basename(str_replace('\\', '/', $modelClass));

        // 转换为小写并用下划线连接
        // 例如: Question -> question, MemberWithdraw -> member_withdraw
        $alias = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $className));

        return $alias;
    }

    /**
     * 批量添加映射（用于动态注册）
     *
     * @param array $maps
     * @return void
     */
    public static function addMaps(array $maps): void
    {
        $existingMaps = self::getAllMaps();
        $newMaps = array_merge($existingMaps, $maps);
        Relation::enforceMorphMap($newMaps);
    }
}
