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

namespace app\service\admin\content\review;

use app\enum\review\ReviewStatus;
use core\foundation\base\BaseModel;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * 审核字段映射服务
 *
 * 功能：
 * 1. 自动扫描并合并系统和所有插件的审核配置（plugin/<插件>/config/review.php）
 * 2. 根据审核类型动态映射字段（title/content/applicant）
 * 3. 支持多种字段映射方式（属性/关联/回调）
 * 4. 插件无需修改系统代码即可扩展审核类型
 * 5. 读取类型级 handler（业务状态回调）与 flow（审批流）声明
 */
class ReviewFieldMapper
{
    /**
     * @var array 缓存的配置（系统 + 所有插件）
     */
    protected static array $configCache = [];

    /**
     * @var array 缓存的字段映射
     */
    protected static array $fieldMappings = [];

    /**
     * @var array 缓存的格式化函数
     */
    protected static array $formatters = [];

    /**
     * 初始化配置（懒加载）
     */
    protected static function init(): void
    {
        if (!empty(self::$configCache)) {
            return;
        }

        $config = config('review', []);
        $pluginConfigs = self::scanPluginConfigs($config['scan_plugins'] ?? true);
        self::$configCache = self::mergeConfigs($config, $pluginConfigs);
        self::buildFieldMappings();
        self::initFormatters();
    }

    /**
     * 扫描插件配置文件
     */
    protected static function scanPluginConfigs(bool $enabled): array
    {
        $pluginConfigs = [];

        if (!$enabled) {
            return $pluginConfigs;
        }

        $ignoredPlugins = config('review.ignored_plugins', []);
        $pluginConfigFile = config('review.plugin_config_file', 'review.php');
        $pluginPath = base_path() . '/plugin';

        if (!is_dir($pluginPath)) {
            return $pluginConfigs;
        }

        $plugins = scandir($pluginPath);
        foreach ($plugins as $plugin) {
            if ($plugin === '.' || $plugin === '..') {
                continue;
            }
            if (in_array($plugin, $ignoredPlugins, true)) {
                continue;
            }

            $configFile = "{$pluginPath}/{$plugin}/config/{$pluginConfigFile}";
            if (!is_file($configFile)) {
                continue;
            }

            $config = include $configFile;
            if (is_array($config)) {
                $config['_plugin'] = [
                    'name' => $plugin,
                    'display_name' => $config['plugin']['display_name'] ?? $plugin,
                ];
                $pluginConfigs[$plugin] = $config;
            }
        }

        return $pluginConfigs;
    }

    /**
     * 合并系统配置和插件配置
     */
    protected static function mergeConfigs(array $systemConfig, array $pluginConfigs): array
    {
        $merged = $systemConfig;

        if (!isset($merged['types'])) {
            $merged['types'] = [];
        }
        if (!isset($merged['field_mappings'])) {
            $merged['field_mappings'] = [];
        }
        if (!isset($merged['default_field_mappings'])) {
            $merged['default_field_mappings'] = [];
        }

        foreach ($pluginConfigs as $pluginName => $pluginConfig) {
            if (isset($pluginConfig['types'])) {
                foreach ($pluginConfig['types'] as $typeKey => $typeConfig) {
                    // 插件配置整体覆盖同名类型（含 handler / flow / label 等）
                    $merged['types'][$typeKey] = $typeConfig;
                }
            }
            if (isset($pluginConfig['field_mappings'])) {
                foreach ($pluginConfig['field_mappings'] as $typeKey => $mappings) {
                    $merged['field_mappings'][$typeKey] = $mappings;
                }
            }
            if (isset($pluginConfig['formatters'])) {
                if (!isset($merged['formatters'])) {
                    $merged['formatters'] = [];
                }
                $merged['formatters'] = array_merge($merged['formatters'], $pluginConfig['formatters']);
            }
        }

        return $merged;
    }

    /**
     * 构建字段映射索引
     *
     * 兼容两种插件声明方式：
     * 1. field_mappings[type] = ['model' => ..., 'fields' => ...]
     * 2. types[type] = ['morph_alias' => ..., 'fields' => ..., 'model' => ...]（portal 等插件常用）
     */
    protected static function buildFieldMappings(): void
    {
        self::$fieldMappings = [];
        $config = self::$configCache;
        $morphMap = self::resolveMorphMap();

        foreach ($config['field_mappings'] ?? [] as $typeKey => $mapping) {
            if (is_string($mapping) && class_exists($mapping)) {
                self::$fieldMappings[$typeKey] = [
                    'model'  => $mapping,
                    'fields' => [],
                ];
            } elseif (is_array($mapping)) {
                self::$fieldMappings[$typeKey] = $mapping;
            }
        }

        // 将 types.*.fields 合并进 field_mappings（插件未写 field_mappings 时的主路径）
        foreach ($config['types'] ?? [] as $typeKey => $typeConfig) {
            if (!is_array($typeConfig)) {
                continue;
            }

            $existing = self::$fieldMappings[$typeKey] ?? [];
            $morphAlias = $typeConfig['morph_alias'] ?? $typeKey;
            $model = $existing['model']
                ?? $typeConfig['model']
                ?? ($morphMap[$morphAlias] ?? null);

            $fields = $existing['fields'] ?? [];
            if (empty($fields) && !empty($typeConfig['fields']) && is_array($typeConfig['fields'])) {
                $fields = $typeConfig['fields'];
            }

            $label = $existing['label']
                ?? $typeConfig['label']
                ?? $typeConfig['display_name']
                ?? $typeKey;

            self::$fieldMappings[$typeKey] = array_merge($existing, [
                'model'        => $model,
                'fields'       => $fields,
                'label'        => $label,
                'display_name' => $existing['display_name']
                    ?? $typeConfig['display_name']
                    ?? $label,
                'morph_alias'  => $morphAlias,
            ]);
        }

        self::$fieldMappings['_by_morph'] = [];
        foreach (self::$fieldMappings as $typeKey => $mapping) {
            if ($typeKey === '_by_morph' || !is_array($mapping)) {
                continue;
            }
            $alias = $mapping['morph_alias'] ?? null;
            if (!$alias) {
                $mappingModel = $mapping['model'] ?? null;
                if ($mappingModel) {
                    $alias = array_search($mappingModel, $morphMap, true) ?: null;
                }
            }
            if (!$alias) {
                $alias = $typeKey;
            }
            self::$fieldMappings['_by_morph'][$alias] = $typeKey;
        }
    }

    /**
     * 解析完整 morph 映射（主配置 + 已注册 Relation + 插件 morph_map 文件）
     */
    protected static function resolveMorphMap(): array
    {
        $morphMap = config('morph_map.map', []);
        if (!is_array($morphMap)) {
            $morphMap = [];
        }

        try {
            if (class_exists(\Illuminate\Database\Eloquent\Relations\Relation::class)) {
                $enforced = \Illuminate\Database\Eloquent\Relations\Relation::morphMap() ?: [];
                if (is_array($enforced) && $enforced !== []) {
                    $morphMap = array_merge($morphMap, $enforced);
                }
            }
        } catch (\Throwable $e) {
            // ignore
        }

        $pluginPath = base_path() . '/plugin';
        if (!is_dir($pluginPath)) {
            return $morphMap;
        }

        foreach (scandir($pluginPath) ?: [] as $plugin) {
            if ($plugin === '.' || $plugin === '..') {
                continue;
            }
            $file = "{$pluginPath}/{$plugin}/config/morph_map.php";
            if (!is_file($file)) {
                continue;
            }
            $cfg = include $file;
            if (!is_array($cfg)) {
                continue;
            }
            if (isset($cfg['map']) && is_array($cfg['map'])) {
                $morphMap = array_merge($morphMap, $cfg['map']);
            } elseif (isset($cfg['morph_map']) && is_array($cfg['morph_map'])) {
                $morphMap = array_merge($morphMap, $cfg['morph_map']);
            } else {
                // 扁平 alias => class
                $flat = [];
                foreach ($cfg as $k => $v) {
                    if (is_string($k) && is_string($v) && class_exists($v)) {
                        $flat[$k] = $v;
                    }
                }
                if ($flat !== []) {
                    $morphMap = array_merge($morphMap, $flat);
                }
            }
        }

        return $morphMap;
    }

    /**
     * 初始化格式化函数
     */
    protected static function initFormatters(): void
    {
        $config = self::$configCache;
        self::$formatters = $config['formatters'] ?? [];

        $builtinFormatters = [
            'trim' => fn($value) => trim($value ?? ''),
            'html_to_text' => fn($html) => strip_tags($html ?? ''),
            'implode' => function ($value, $glue = ',') {
                return is_array($value) ? implode($glue, $value) : $value;
            },
            'datetime' => function ($timestamp, $format = 'Y-m-d H:i:s') {
                return is_numeric($timestamp) ? date($format, (int)$timestamp) : $timestamp;
            },
            'limit' => function ($text, $length = 50) {
                return Str::limit($text, $length);
            },
        ];

        self::$formatters = array_merge($builtinFormatters, self::$formatters);
    }

    /**
     * 格式化字段值
     */
    protected static function formatValue($value, ?array $fieldConfig): mixed
    {
        if ($value === null || empty($fieldConfig)) {
            return $value;
        }

        $format = $fieldConfig['format'] ?? null;
        if ($format) {
            if (is_string($format) && str_contains($format, ':')) {
                [$func, $param] = explode(':', $format, 2);
                $formatter = self::$formatters[$func] ?? null;
                if ($formatter) {
                    return $formatter($value, $param);
                }
            } elseif (isset(self::$formatters[$format])) {
                return self::$formatters[$format]($value);
            }
        }

        return $value;
    }

    /**
     * 根据 morph_map 别名获取字段配置
     */
    protected static function getFieldConfigByMorphAlias(string $morphAlias): ?array
    {
        $config = self::$fieldMappings['_by_morph'][$morphAlias] ?? null;
        if (!$config) {
            return null;
        }
        return self::$fieldMappings[$config] ?? null;
    }

    /**
     * 根据审核记录获取字段映射配置
     */
    protected static function getFieldConfig(BaseModel $review): ?array
    {
        $reviewableType = $review->reviewable_type;

        // reviewable_type 通常存 morph 别名（如 question）
        $byAlias = self::getFieldConfigByMorphAlias((string) $reviewableType);
        if ($byAlias) {
            return $byAlias;
        }

        if (isset(self::$fieldMappings[$reviewableType]) && is_array(self::$fieldMappings[$reviewableType])) {
            return self::$fieldMappings[$reviewableType];
        }

        // 兼容存完整类名的历史数据
        $morphMap = self::resolveMorphMap();
        $morphAlias = array_search($reviewableType, $morphMap, true);
        if ($morphAlias !== false) {
            $config = self::getFieldConfigByMorphAlias($morphAlias);
            if ($config) {
                return $config;
            }
            return self::$fieldMappings[$morphAlias] ?? null;
        }

        return null;
    }

    /**
     * 获取单个字段的值
     */
    protected static function getFieldValue($model, string $fieldName, ?array $fieldConfig): mixed
    {
        if (!$model || empty($fieldConfig)) {
            return is_array($fieldConfig) ? ($fieldConfig['fallback'] ?? null) : null;
        }

        $type = $fieldConfig['type'] ?? 'attribute';
        $source = $fieldConfig['source'] ?? $fieldName;

        switch ($type) {
            case 'attribute':
                $value = $model->{$source} ?? null;
                break;
            case 'relation':
                if (method_exists($model, $source)) {
                    $relation = $model->{$source};
                    $value = $relation ? ($relation->{$fieldConfig['attribute'] ?? 'name'} ?? null) : null;
                } else {
                    $value = null;
                }
                break;
            case 'callback':
                $callback = $fieldConfig['callback'] ?? null;
                $value = is_callable($callback) ? $callback($model) : null;
                break;
            case 'fixed':
                $value = $fieldConfig['value'] ?? null;
                break;
            default:
                $value = null;
        }

        if ($value === null || $value === '') {
            $value = $fieldConfig['fallback'] ?? null;
        }

        return self::formatValue($value, $fieldConfig);
    }

    /**
     * 映射审核记录字段（兼容运行表 Review 与归档表 ReviewArchive）
     */
    public static function mapReview(BaseModel $review, array $extraFields = []): array
    {
        self::init();

        $fieldConfig = self::getFieldConfig($review) ?? [];
        $typeFields = is_array($fieldConfig['fields'] ?? null) ? $fieldConfig['fields'] : [];
        $extraData = is_array($review->extra_data) ? $review->extra_data : [];

        $needsLive = empty($extraData);
        if (!$needsLive) {
            // 历史脏快照（未命名/无内容）需要回源业务表补全
            foreach (['title', 'content', 'applicant'] as $field) {
                $item = $typeFields[$field] ?? self::$configCache['default_field_mappings'][$field] ?? null;
                $fallback = is_array($item) ? ($item['fallback'] ?? null) : null;
                $snap = $extraData[$field] ?? null;
                if (self::isPlaceholderSnapshotValue($snap, $fallback)) {
                    $needsLive = true;
                    break;
                }
            }
        }

        $reviewable = null;
        if ($needsLive) {
            $reviewable = self::resolveReviewable($review, $fieldConfig ?: null);
        }

        $fields = [];

        // 标准字段：有效快照优先；占位快照或缺失时回源业务模型
        foreach (['title', 'content', 'applicant'] as $field) {
            $fieldConfigItem = $typeFields[$field] ?? self::$configCache['default_field_mappings'][$field] ?? null;
            $fallback = is_array($fieldConfigItem) ? ($fieldConfigItem['fallback'] ?? null) : null;

            if (array_key_exists($field, $extraData)
                && !self::isPlaceholderSnapshotValue($extraData[$field], $fallback)
            ) {
                $fields[$field] = $extraData[$field];
                continue;
            }

            $fields[$field] = self::getFieldValue($reviewable, $field, is_array($fieldConfigItem) ? $fieldConfigItem : null);
        }

        foreach ($extraFields as $field) {
            if (isset($typeFields[$field]) && is_array($typeFields[$field])) {
                $fields[$field] = self::getFieldValue($reviewable, $field, $typeFields[$field]);
            }
        }

        $fields = array_merge($fields, self::getBaseReviewFields($review, $fieldConfig));

        return $fields;
    }

    /**
     * 快照值是否为占位默认值（创建时未正确映射产生）
     */
    protected static function isPlaceholderSnapshotValue(mixed $value, mixed $fallback): bool
    {
        if ($value === null || $value === '') {
            return true;
        }
        if (!is_string($value)) {
            return false;
        }
        if ($fallback !== null && $value === $fallback) {
            return true;
        }
        return in_array($value, ['未命名', '无内容', '未知', '数据不存在'], true);
    }

    /**
     * 解析业务对象：优先 morph 关联，失败则按 field_mappings.model + reviewable_id 直查
     */
    protected static function resolveReviewable(BaseModel $review, ?array $fieldConfig): mixed
    {
        if (method_exists($review, 'reviewable')) {
            try {
                $reviewable = $review->reviewable;
                if ($reviewable) {
                    return $reviewable;
                }
            } catch (\Throwable $e) {
                // fall through
            }
        }

        $modelClass = $fieldConfig['model'] ?? null;
        $id = $review->reviewable_id ?? null;
        if (!$modelClass || $id === null || $id === '' || !class_exists($modelClass)) {
            $type = (string) ($review->reviewable_type ?? '');
            $modelClass = self::getModelClass($type);
        }
        if (!$modelClass || $id === null || $id === '' || !class_exists($modelClass)) {
            return null;
        }

        try {
            return $modelClass::find($id);
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * 批量映射审核记录
     */
    public static function mapReviews(Collection $reviews, array $extraFields = []): Collection
    {
        return $reviews->map(function (BaseModel $review) use ($extraFields) {
            $mapped = self::mapReview($review, $extraFields);
            foreach ($mapped as $key => $value) {
                if (!isset($review->{$key})) {
                    $review->{$key} = $value;
                }
            }
            return $review;
        });
    }

    /**
     * 获取审核基础字段（兼容运行/归档模型）
     */
    protected static function getBaseReviewFields(BaseModel $review, ?array $fieldConfig = null): array
    {
        $statusText = method_exists($review, 'statusText')
            ? $review->statusText()
            : ReviewStatus::fromValue($review->status)->label();

        $reviewerName = null;
        if (method_exists($review, 'reviewer')) {
            $reviewerName = $review->reviewer?->real_name;
        }

        return [
            'id' => $review->id,
            'reviewable_type' => $review->reviewable_type,
            'reviewable_id' => $review->reviewable_id,
            'status' => $review->status,
            'status_text' => $statusText,
            'reason' => $review->reason,
            'reviewer_id' => $review->reviewer_id,
            'reviewer_name' => $reviewerName,
            'reviewed_at' => $review->reviewed_at,
            'cancel_reason' => $review->cancel_reason ?? null,
            'flow_type' => $review->flow_type ?? 'simple',
            'flow_instance_id' => $review->flow_instance_id ?? null,
            'created_at' => $review->created_at,
            'updated_at' => $review->updated_at,
            'display_name' => $fieldConfig['display_name'] ?? $review->reviewable_type,
            'morph_alias' => self::getMorphAlias($review->reviewable_type),
        ];
    }

    /**
     * 获取 fallback 字段（当找不到配置或关联对象时）
     */
    protected static function getFallbackFields(BaseModel $review): array
    {
        return array_merge(
            self::getBaseReviewFields($review),
            [
                'title' => '数据不存在',
                'content' => '',
                'applicant' => '未知',
            ]
        );
    }

    /**
     * 获取所有已配置的审核类型（用于下拉）
     */
    public static function getTypes(): array
    {
        self::init();
        $result = [];
        foreach (self::$configCache['types'] ?? [] as $key => $config) {
            $result[] = [
                'value' => $key,
                'label' => $config['label'] ?? $config['display_name'] ?? $key,
            ];
        }
        return $result;
    }

    /**
     * 获取指定类型的原始配置（含 handler / flow / label）
     */
    public static function getTypeConfig(string $typeKey): ?array
    {
        self::init();
        return self::$configCache['types'][$typeKey] ?? null;
    }

    /**
     * 获取类型声明的业务处理器类
     */
    public static function getHandlerClass(string $typeKey): ?string
    {
        $config = self::getTypeConfig($typeKey);
        return $config['handler'] ?? null;
    }

    /**
     * 获取类型对应的业务模型类（用于按类型导航关联业务表）
     */
    public static function getModelClass(string $typeKey): ?string
    {
        self::init();
        $mapping = self::$fieldMappings[$typeKey] ?? null;
        if (is_array($mapping) && !empty($mapping['model']) && class_exists($mapping['model'])) {
            return $mapping['model'];
        }
        // 兼容 field_mappings 直接为模型类名字符串的形式
        if (is_string($mapping) && class_exists($mapping)) {
            return $mapping;
        }

        // types / morph 别名兜底
        $byMorphKey = self::$fieldMappings['_by_morph'][$typeKey] ?? null;
        if ($byMorphKey && isset(self::$fieldMappings[$byMorphKey])) {
            $m = self::$fieldMappings[$byMorphKey]['model'] ?? null;
            if (is_string($m) && class_exists($m)) {
                return $m;
            }
        }

        $morphMap = self::resolveMorphMap();
        $fromMorph = $morphMap[$typeKey] ?? null;
        if (is_string($fromMorph) && class_exists($fromMorph)) {
            return $fromMorph;
        }

        return null;
    }

    /**
     * 解析 morph 别名：支持已是别名，或完整类名
     */
    protected static function getMorphAlias(string $typeOrClass): ?string
    {
        $morphMap = self::resolveMorphMap();
        if (isset($morphMap[$typeOrClass])) {
            return $typeOrClass;
        }
        $alias = array_search($typeOrClass, $morphMap, true);
        return $alias !== false ? $alias : $typeOrClass;
    }

    /**
     * 创建审核时固化业务表单数据快照。
     *
     * 这是"审核表自带表单数据"的核心：优先从已配置的业务模型读取 title/content/applicant
     * 等字段，使审核记录在展示/审批时自包含、无需回查业务表；调用方显式传入的 extra_data
     * 拥有最高优先级（例如前端已提交的最新表单值）。
     */
    public static function captureSnapshot(string $type, $id, array $provided = []): array
    {
        self::init();
        $modelClass = self::getModelClass($type);
        $business = null;
        if ($modelClass) {
            try {
                $business = $modelClass::find($id);
            } catch (\Throwable $e) {
                $business = null;
            }
        }

        $config = self::$fieldMappings[$type] ?? [];
        if (empty($config['fields'])) {
            $mappedKey = self::$fieldMappings['_by_morph'][$type] ?? null;
            if ($mappedKey && isset(self::$fieldMappings[$mappedKey])) {
                $config = self::$fieldMappings[$mappedKey];
            }
        }

        $typeFields = is_array($config['fields'] ?? null) ? $config['fields'] : [];
        $fieldNames = array_unique(array_merge(
            ['title', 'content', 'applicant'],
            array_keys($typeFields)
        ));

        $snapshot = [];
        foreach ($fieldNames as $field) {
            $fieldConfig = $typeFields[$field]
                ?? self::$configCache['default_field_mappings'][$field]
                ?? null;
            $value = self::getFieldValue($business, $field, is_array($fieldConfig) ? $fieldConfig : null);
            if ($value !== null && $value !== '') {
                $snapshot[$field] = $value;
            }
        }

        // 调用方显式传入优先
        return array_merge($snapshot, $provided);
    }

    /**
     * 该类型是否启用外部审批流（全局开关 + 类型级覆盖）
     */
    public static function isFlowEnabled(string $typeKey): bool
    {
        $config = self::getTypeConfig($typeKey);
        $flow = $config['flow'] ?? null;
        if (is_array($flow) && array_key_exists('enabled', $flow)) {
            return !empty($flow['enabled']);
        }
        return (bool)config('review.flow.enabled', false);
    }
}
