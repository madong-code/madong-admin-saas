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

namespace app\service\api\system;

use app\dao\system\config\ConfigDao;
use app\scope\global\AccessPermissionScope;
use core\foundation\base\BaseService;
use core\io\upload\UploadFile;


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
        $fallbackGroups = $options['fallback_groups'] ?? [];
        $primaryGroup   = $options['group_code'] ?? '';

        // 如果指定了主分组，优先在该分组中查询
        if (!empty($primaryGroup)) {
            $map['group_code'] = $primaryGroup;
        }

        // 查询配置
        $configModel = $this->dao->get($map, ['*'], [], '', [AccessPermissionScope::class]);

        // 主分组未找到且配置了 fallback 分组，逐个回退查找
        if (!$configModel && !empty($fallbackGroups) && is_array($fallbackGroups)) {
            foreach ($fallbackGroups as $fbGroup) {
                if ($fbGroup === $primaryGroup) {
                    continue;
                }
                $configModel = $this->dao->get(
                    ['code' => $code, 'group_code' => $fbGroup],
                    ['*'], [], '', [AccessPermissionScope::class]
                );
                if ($configModel) {
                    break;
                }
            }
        }

        // 如果配置不存在，自动创建到主分组或 default
        if (!$configModel) {
            $targetGroup = $primaryGroup ?: 'default';
            $configData = [
                'code' => $code,
                'name' => $options['name'] ?? $code,
                'group_code' => $targetGroup,
                'content' => is_array($default) ? json_encode($default, JSON_UNESCAPED_UNICODE) : $default,
                'type' => $options['type'] ?? (is_array($default) ? 'json' : 'string'),
                'enabled' => 1,
                'sort' => 0,
                'remark' => $options['remark'] ?? '',
            ];

            try {
                $this->dao->save($configData);
            } catch (\Exception $e) {
            }

            return !empty($options['with_upload_info']) && is_array($default)
                ? $this->appendUploadInfo($default)
                : $default;
        }

        $content = $configModel->getOriginal('content',null);

        // 如果content是数组/JSON格式，返回整个数组；否则返回原值
        if (is_string($content) && !empty($content)) {
            $decoded = json_decode($content, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return !empty($options['with_upload_info']) && is_array($decoded)
                    ? $this->appendUploadInfo($decoded)
                    : $decoded;
            }
            return $content;
        }

        return $content ?? $default;
    }

    /**
     * 追加存储/上传的运行时信息
     *
     * 前端需据此决定资源地址的拼接方式：
     * - upload_mode=local：资源与站点同域，直接用相对路径即可
     * - upload_mode=qiniu/oss/cos/s3：资源在云存储，必须用 cdn_url 前缀拼接，
     *   否则会回落到站点域名请求本地文件而 404
     *
     * 注意：cdn_url 为运行时推算结果，始终反映当前真实存储位置，
     * 不读取 site_setting 中可能残留的同名字段。
     *
     * @param array $config
     *
     * @return array
     */
    private function appendUploadInfo(array $config): array
    {
        $info = UploadFile::runtimeInfo();

        $config['upload_mode']    = $info['mode'];
        $config['cdn_url']        = $info['cdn_url'];
        $config['static_url']     = $info['cdn_url'];
        $config['cdn_url_params'] = $info['cdn_url_params'];
        // 私有空间（非公开读）：与驱动配置、runtimeInfo() 保持同一 key（is_private）
        // 前端不能自行拼接，须按资源 key 调 /api/file/access-urls 换取地址
        $config['is_private']     = $info['is_private'];
        $config['storage_prefix'] = $info['storage_prefix'];

        return $config;
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
            $configs = $this->dao->getList($map);
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

}