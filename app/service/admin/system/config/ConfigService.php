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

namespace app\service\admin\system\config;

use app\dao\system\config\ConfigDao;
use app\scope\global\AccessPermissionScope;
use core\foundation\base\BaseService;

class ConfigService extends BaseService
{

    public function __construct(ConfigDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 获取配置项
     *
     * @param string $code    配置code
     * @param mixed  $default 默认值
     * @param array  $options 选项，如 ['group_code' => '...']
     *
     * @return mixed
     * @throws \Exception
     */
    public function config(string $code, mixed $default = [], array $options = []): mixed
    {
        $map = ['code' => $code];
        // 如果选项中有分组，则添加到查询条件
        if (!empty($options['group_code'])) {
            $map['group_code'] = $options['group_code'];
        }

        // 查询配置（TenantScope 会自动按当前租户过滤）
        $configModel = $this->dao->get($map, ['*'], [], '', [AccessPermissionScope::class]);

        // 没有配置直接返回默认值
        if (!$configModel) {
            return $default;
        }

        $content = $configModel->getOriginal('content', null);

        // 如果content是数组/JSON格式，返回整个数组；否则返回原值
        if (is_string($content) && !empty($content)) {
            $decoded = json_decode($content, true);
            return json_last_error() === JSON_ERROR_NONE ? $decoded : $content;
        }

        return $content ?? $default;
    }

    /**
     * 获取站点配置
     *
     * - 租户模式 → 取 platform 分组下的平台配置
     * - 单体模式 → 取 default 分组下的默认配置
     *
     * @param array $default 默认值
     * @return array
     * @throws \Exception
     */
    public function getSiteConfig(array $default = []): array
    {
        $groupCode = config('tenant.enable', false) ? 'platform' : 'default';
        $code      = 'site_setting';

        $config = $this->dao->getModel()
            ->withoutGlobalScopes()
            ->where('group_code', $groupCode)
            ->where('code', $code)
            ->where('enabled', 1)
            ->whereNull('tenant_id')
            ->first();

        if (!$config) {
            return $default;
        }

        $content = $config->getRawOriginal('content');
        if (is_string($content) && !empty($content)) {
            $decoded = json_decode($content, true);
            return json_last_error() === JSON_ERROR_NONE ? $decoded : $content;
        }

        return $content ?? $default;
    }

    /**
     * 获取配置分组详情
     *
     * @param string $groupCode 配置分组
     * @param array  $defaults  默认配置项
     * @param array  $options   选项参数
     *                          - enabled_only: bool 是否只获取启用的配置项，默认true
     *                          - with_metadata: bool 是否包含配置元数据，默认false
     *
     * @return array
     * @throws \ReflectionException
     */
    public function getByGroup(string $groupCode, array $defaults = [], array $options = []): array
    {
        // 验证groupCode参数，防止SQL注入
        if (empty($groupCode)) {
            return $defaults;
        }

        $enabledOnly  = $options['enabled_only'] ?? true;
        $withMetadata = $options['with_metadata'] ?? false;

        $map = ['group_code' => $groupCode];
        if ($enabledOnly) {
            $map['enabled'] = 1;
        }

        if ($withMetadata) {
            // 获取完整配置信息
            $configs       = $this->dao->getList($map);
            $processedData = [];
            foreach ($configs as $config) {
                $content = $config['content'] ?? null;
                if (is_string($content) && !empty($content)) {
                    $decoded           = json_decode($content, true);
                    $config['content'] = json_last_error() === JSON_ERROR_NONE ? $decoded : $content;
                }

                if (isset($options['key_by']) && $options['key_by'] === 'code') {
                    $processedData[$config['code']] = $config;
                } else {
                    $processedData[] = $config;
                }
            }

            return $processedData;
        } else {
            // 只获取配置内容
            $data = $this->dao->getColumn($map, 'content', 'code');
            // 处理每个配置项的内容，如果是JSON格式则解码
            $processedData = [];
            foreach ($data as $code => $content) {
                if (is_string($content) && !empty($content)) {
                    $decoded              = json_decode($content, true);
                    $processedData[$code] = json_last_error() === JSON_ERROR_NONE ? $decoded : $content;
                } else {
                    $processedData[$code] = $content;
                }
            }

            // 合并默认配置
            return array_merge($defaults, $processedData);
        }
    }

    /**
     * 获取所有配置并按分组整理
     *
     * @param array $options 选项参数
     *                       - enabled_only: bool 是否只获取启用的配置项，默认true
     *                       - group_filter: array 分组过滤，只返回指定分组的配置
     *                       - with_metadata: bool 是否包含配置项的完整元数据，默认false
     *                       - key_by: string 按指定字段作为键名（'code' 或 'id'），默认null
     *                       - sort_groups: bool 是否对分组进行排序，默认false
     *                       - sort_configs: bool 是否对配置项进行排序，默认false
     *
     * @return array
     * @throws \Exception
     */
    public function getAllGrouped(array $options = []): array
    {
        $enabledOnly  = $options['enabled_only'] ?? true;
        $groupFilter  = $options['group_filter'] ?? [];
        $withMetadata = $options['with_metadata'] ?? false;
        $keyBy        = $options['key_by'] ?? null;
        $sortGroups   = $options['sort_groups'] ?? false;
        $sortConfigs  = $options['sort_configs'] ?? false;

        $map = [];
        if ($enabledOnly) {
            $map['enabled'] = 1;
        }
        if (!empty($groupFilter)) {
            $map['group_code'] = $groupFilter;
        }

        $configs = $this->dao->getList($map);
        $grouped = [];

        foreach ($configs as $config) {
            $groupCode = $config['group_code'] ?? 'default';

            // 处理内容
            $content = $config['content'];
            if (is_string($content) && !empty($content)) {
                $decoded           = json_decode($content, true);
                $config['content'] = json_last_error() === JSON_ERROR_NONE ? $decoded : $content;
            }

            // 初始化分组
            if (!isset($grouped[$groupCode])) {
                $grouped[$groupCode] = [];
            }

            // 根据选项决定返回的数据结构
            if ($withMetadata) {
                // 返回完整配置信息
                if ($keyBy && isset($config[$keyBy])) {
                    $grouped[$groupCode][$config[$keyBy]] = $config;
                } else {
                    $grouped[$groupCode][] = $config;
                }
            } else {
                // 只返回配置内容
                if ($keyBy && isset($config[$keyBy])) {
                    $grouped[$groupCode][$config[$keyBy]] = $config['content'];
                } else {
                    $grouped[$groupCode][$config['code']] = $config['content'];
                }
            }
        }

        // 排序处理
        if ($sortGroups) {
            ksort($grouped);
        }

        if ($sortConfigs) {
            foreach ($grouped as &$configs) {
                if ($withMetadata) {
                    // 对元数据模式按code排序
                    uasort($configs, function ($a, $b) {
                        return strcmp($a['code'] ?? '', $b['code'] ?? '');
                    });
                } else {
                    // 对内容模式按键名排序
                    ksort($configs);
                }
            }
        }

        return $grouped;
    }

    /**
     * 获取配置项中的某个键值
     *
     * @param string $code    配置项
     * @param string $key     要获取的键，支持多级键，如 key1.key2.key3
     * @param mixed  $default 默认值，默认null
     * @param array  $options 选项参数
     *                        - group_code: string 配置分组，默认null（在所有分组中查找）
     *                        - require_group: bool 是否必须指定分组，默认false
     *                        - fallback_to_all: bool 当未找到分组配置时，是否在所有分组中查找，默认true
     *                        - search_groups: array 搜索的分组列表，按顺序查找
     *
     * @return mixed
     * @throws \Exception
     */
    public function getValue(string $code, string $key, mixed $default = null, array $options = []): mixed
    {
        $groupCode     = $options['group_code'] ?? null;
        $requireGroup  = $options['require_group'] ?? false;
        $fallbackToAll = $options['fallback_to_all'] ?? true;
        $searchGroups  = $options['search_groups'] ?? [];

        // 如果指定了搜索分组列表，按顺序查找
        if (!empty($searchGroups) && is_array($searchGroups)) {
            foreach ($searchGroups as $searchGroupCode) {
                $config = $this->config($code, null, ['group_code' => $searchGroupCode]);
                if (is_array($config)) {
                    $value = $this->getNestedValue($config, $key);
                    if ($value !== null) {
                        return $value;
                    }
                }
            }
            return $default;
        }

        // 如果指定了分组，优先在该分组中查找
        if ($groupCode !== null) {
            $config = $this->config($code, null, ['group_code' => $groupCode]);

            if (is_array($config)) {
                $value = $this->getNestedValue($config, $key);
                if ($value !== null) {
                    return $value;
                }
            }

            // 如果未找到且允许回退到所有分组，则尝试在所有分组中查找
            if ($fallbackToAll && !$requireGroup) {
                $config = $this->config($code, null);
                if (is_array($config)) {
                    $value = $this->getNestedValue($config, $key);
                    if ($value !== null) {
                        return $value;
                    }
                }
            }

            return $default;
        }

        // 默认在所有分组中查找
        $config = $this->config($code, null);
        if (is_array($config)) {
            $value = $this->getNestedValue($config, $key);
            if ($value !== null) {
                return $value;
            }
        }

        return $default;
    }

    /**
     * 设置特定配置项中的某个键值
     * 支持两种调用方式：
     * 1. 新版本（推荐）：setConfigValue(string $code, string $key, mixed $value, array $options = [])
     * 2. 旧版本（兼容）：setConfigValue(string $groupCode, string $code, string $key, mixed $value)
     *
     * @param string      $code      配置项或配置分组（旧版本）
     * @param string      $key       要设置的键，支持多级键，如 key1.key2.key3 或配置项（旧版本）
     * @param mixed       $value     要设置的值或要设置的键（旧版本）
     * @param array|mixed $options   选项参数或要设置的值（旧版本）
     *                               - group_code: string 配置分组，默认null
     *
     * @return bool
     * @throws \Exception
     */
    public function setValue(string $code, string $key, mixed $value, mixed $options = []): bool
    {
        // 检查是否为旧版本调用方式：setConfigValue($groupCode, $code, $key, $value)
        if (func_num_args() === 4 && is_string($options)) {
            // 旧版本调用，参数顺序为：$groupCode, $code, $key, $value
            $groupCode = $code;
            $code      = $key;
            $key       = $value;
            $value     = $options;
            $options   = ['group_code' => $groupCode];
        }

        $groupCode = $options['group_code'] ?? null;

        // 验证groupCode参数，防止SQL注入
        if ($groupCode !== null && !preg_match('/^[a-zA-Z0-9_-]+$/', $groupCode)) {
            throw new \Exception('Invalid group_code format');
        }

        $config = $this->config($code, [], ['group_code' => $groupCode]);

        if (is_array($config)) {
            // 支持多级键设置
            $updatedConfig = $this->setNestedValue($config, $key, $value);
        } else {
            // 如果当前配置不是数组，将其转换为数组
            $updatedConfig = $this->setNestedValue([], $key, $value);
        }

        // 更新配置，传递groupCode参数
        return $this->update($code, $updatedConfig, ['group_code' => $groupCode]);
    }

    /**
     * 根据多级键获取嵌套数组中的值
     *
     * @param array  $array 要查找的数组
     * @param string $key   多级键，如 key1.key2.key3
     *
     * @return mixed|null 找到的值或null
     */
    private function getNestedValue(array $array, string $key): mixed
    {
        // 如果key不包含点号，直接返回对应值
        if (!str_contains($key, '.')) {
            return $array[$key] ?? null;
        }

        // 拆分多级键
        $keys  = explode('.', $key);
        $value = $array;

        // 逐级查找
        foreach ($keys as $k) {
            if (!is_array($value) || !isset($value[$k])) {
                return null;
            }
            $value = $value[$k];
        }

        return $value;
    }

    /**
     * 根据多级键设置嵌套数组中的值
     *
     * @param array  $array 要设置值的数组
     * @param string $key   多级键，如 key1.key2.key3
     * @param mixed  $value 要设置的值
     *
     * @return array 更新后的数组
     */
    private function setNestedValue(array $array, string $key, mixed $value): array
    {
        // 如果key不包含点号，直接设置值
        if (!str_contains($key, '.')) {
            $array[$key] = $value;
            return $array;
        }

        // 拆分多级键
        $keys    = explode('.', $key);
        $lastKey = array_pop($keys);
        $current = &$array;

        // 逐级创建或获取子数组
        foreach ($keys as $k) {
            if (!isset($current[$k]) || !is_array($current[$k])) {
                $current[$k] = [];
            }
            $current = &$current[$k];
        }

        // 设置最终值
        $current[$lastKey] = $value;

        return $array;
    }

    /**
     * 更新配置
     *
     * @param string $code    配置code
     * @param mixed  $content 配置内容
     * @param array  $options 扩展选项参数
     *                        - group_code: string 配置分组
     *                        - name: string 配置名称
     *                        - enabled: int 是否启用 (1=启用, 0=禁用)
     *
     * @return void
     * @throws \Exception
     */
    public function update(string $code, mixed $content, array $options = []): void
    {
        try {
            // 处理内容：如果是数组则转换为JSON，字符串直接存储
            $processedContent = $content;
            if (is_array($content)) {
                $processedContent = json_encode($content, JSON_UNESCAPED_UNICODE);
            }

            $map         = ['code' => $code];
            $configModel = $this->dao->get($map);

            if (!empty($configModel)) {
                // 更新内容
                $configModel->content = $processedContent;
                // 更新其他可选字段
                if (isset($options['group_code'])) {
                    // 验证group_code格式
                    if (!preg_match('/^[a-zA-Z0-9_-]+$/', $options['group_code'])) {
                        throw new \Exception('Invalid group_code format');
                    }
                    $configModel->group_code = $options['group_code'];
                }

                if (isset($options['name'])) {
                    $configModel->name = $options['name'];
                }

                if (isset($options['enabled'])) {
                    $configModel->enabled = (int)$options['enabled'];
                }

                $configModel->save();
            } else {
                // 创建新的配置项
                $data = [
                    'code'    => $code,
                    'content' => $processedContent,
                    'name'    => $options['name'] ?? $code,
                    'enabled' => isset($options['enabled']) ? (int)$options['enabled'] : 1,
                ];

                // 设置可选的分组
                if (isset($options['group_code'])) {
                    // 验证group_code格式
                    if (!preg_match('/^[a-zA-Z0-9_-]+$/', $options['group_code'])) {
                        throw new \Exception('Invalid group_code format');
                    }
                    $data['group_code'] = $options['group_code'];
                }

                $this->dao->save($data);
            }
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage());
        }
    }

    /**
     * 检查配置项是否存在
     *
     * @param string $code    配置code
     * @param array  $options 扩展选项参数
     *                        - group_code: string 配置分组
     *
     * @return bool
     * @throws \Exception
     */
    public function exists(string $code, array $options = []): bool
    {
        $map = ['code' => $code];
        if (isset($options['group_code'])) {
            // 验证group_code格式
            if (!preg_match('/^[a-zA-Z0-9_-]+$/', $options['group_code'])) {
                throw new \Exception('Invalid group_code format');
            }
            $map['group_code'] = $options['group_code'];
        }

        $config = $this->dao->get($map);
        return !empty($config);
    }

    /**
     * 删除配置项
     *
     * @param string $code     配置code
     * @param array  $options  扩展参数
     *                         - group_code: string 配置分组
     *
     * @return bool
     * @throws \Exception
     */
    public function delete(string $code, array $options = []): bool|int
    {
        $map = [['code' ,'EQ', $code]];
        if (isset($options['group_code'])) {
            // 验证group_code格式
            if (!preg_match('/^[a-zA-Z0-9_-]+$/', $options['group_code'])) {
                throw new \Exception('Invalid group_code format');
            }
            $map[] = ['group_code','EQ',$options['group_code']];
        }
        $config = $this->dao->get($map);
        if (!$config) {
            return false;
        }
        // 验证是否为系统配置，系统配置不允许删除
        if (isset($config->is_sys) && $config->is_sys == 1) {
            throw new \Exception('系统配置不允许删除');
        }

        return $this->dao->delete($config['id']);
    }

    /**
     * 启用/禁用配置项
     *
     * @param string $code     配置code
     * @param int    $enabled
     * @param array  $options  扩展参数
     *                         - group_code: string 配置分组
     *                         - enabled: bool 是否启用，默认true
     *
     * @return bool
     * @throws \Exception
     */
    public function enabled(string $code, int $enabled, array $options = []): bool
    {
        $map = ['code' => $code];
        if (isset($options['group_code'])) {
            // 验证group_code格式
            if (!preg_match('/^[a-zA-Z0-9_-]+$/', $options['group_code'])) {
                throw new \Exception('Invalid group_code format');
            }
            $map['group_code'] = $options['group_code'];
        }

        $config = $this->dao->get($map);
        if (!$config) {
            return false;
        }
        $config->enabled = $enabled;
        return $config->save();
    }

    /**
     * 获取配置项列表（分页）
     *
     * @param int    $page      页码
     * @param int    $pageSize  每页数量
     * @param string $groupCode 分组代码
     * @param string $keyword   搜索关键字
     *
     * @return array
     * @throws \Exception
     */
    public function getItems(int $page, int $pageSize, string $groupCode, string $keyword = ''): array
    {

        $map1 = [['is_sys', 'ne', 1]];
        if (!empty($keyword)) {
            $map1[] = ['code', 'eq', $keyword];
        }

        $list  = $this->dao->getList($map1, ['*'], $page, $pageSize, 'code', [], false);
        $total = $this->dao->count($map1);
        // 处理每个配置项的值：自动检测 JSON
        foreach ($list as &$item) {
            $item['value'] = $this->formatValue($item['content']);
        }
        return [
            'items' => $list,
            'total' => $total,
        ];
    }

    /**
     * 创建单个配置项
     *
     * @param array $data 配置数据
     *
     * @return void
     * @throws \Exception
     */
    public function createItem(array $data): void
    {
        // 检查配置代码是否已存在
        if ($this->exists($data['code'], ['group_code' => $data['group_code']])) {
            throw new \Exception('配置代码已存在');
        }

        $this->dao->save($data);
    }

    /**
     * 更新单个配置项
     *
     * @param int   $id   配置ID
     * @param array $data 配置数据
     *
     * @return void
     * @throws \Exception
     */
    public function updateItem(int $id, array $data): void
    {
        $config = $this->dao->get(['id' => $id]);
        if (!$config) {
            throw new \Exception('配置不存在');
        }

        // 验证是否为系统配置
        if (isset($config->is_sys) && $config->is_sys == 1) {
            throw new \Exception('系统配置不允许修改');
        }

        $config->name        = $data['name'];
        $config->content     = $data['content'];
        $config->description = $data['description'] ?? '';
        $config->enabled     = $data['enabled'] ?? 1;

        $config->save();
    }

    /**
     * 删除配置项
     *
     * @param int $id 配置ID
     *
     * @return void
     * @throws \Exception
     */
    public function deleteItem(int $id): void
    {
        $config = $this->dao->get(['id' => $id]);
        if (!$config) {
            throw new \Exception('配置不存在');
        }

        // 验证是否为系统配置
        if (isset($config->is_sys) && $config->is_sys == 1) {
            throw new \Exception('系统配置不允许删除');
        }

        $this->dao->delete($id);
    }

    /**
     * 切换配置项启用状态
     *
     * @param int  $id      配置ID
     * @param bool $enabled 是否启用
     *
     * @return void
     * @throws \Exception
     */
    public function toggleItem(int $id, bool $enabled): void
    {
        $config = $this->dao->get(['id' => $id]);
        if (!$config) {
            throw new \Exception('配置不存在');
        }

        $config->enabled = $enabled ? 1 : 0;
        $config->save();
    }

    /**
     * 格式化配置值：自动检测 JSON
     *
     * @param mixed $content 配置内容
     *
     * @return mixed
     */
    private function formatValue(mixed $content): mixed
    {
        if (is_string($content) && !empty($content)) {
            $decoded = json_decode($content, true);
            return json_last_error() === JSON_ERROR_NONE ? $decoded : $content;
        }
        return $content;
    }
}