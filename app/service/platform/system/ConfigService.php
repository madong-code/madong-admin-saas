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
namespace app\service\platform\system;

use app\dao\system\config\ConfigDao;
use core\business\tenant\scope\TenantScope;
use core\foundation\base\BaseService;

/**
 * 平台端系统配置 Service
 *
 * 平台配置以 tenant_id = null 存储在 sys_config 表中，与租户配置完全隔离。
 * 所有查询/写入均绕过 TenantScope 全局作用域，并显式过滤 tenant_id IS NULL。
 */
class ConfigService extends BaseService
{
    public function __construct(ConfigDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 获取平台配置项
     */
    public function config(string $code, mixed $default = [], array $options = []): mixed
    {
        $query = $this->dao->getModel()
            ->withoutGlobalScope(TenantScope::class)
            ->where('code', $code)
            ->whereNull('tenant_id');

        if (!empty($options['group_code'])) {
            $query->where('group_code', $options['group_code']);
        }

        $configModel = $query->first();

        if (!$configModel) {
            // 自动创建
            $configData = [
                'code'       => $code,
                'name'       => $options['name'] ?? $code,
                'group_code' => $options['group_code'] ?? 'default',
                'content'    => is_array($default) ? json_encode($default, JSON_UNESCAPED_UNICODE) : $default,
                'type'       => $options['type'] ?? (is_array($default) ? 'json' : 'string'),
                'enabled'    => 1,
                'sort'       => 0,
                'remark'     => $options['remark'] ?? '',
                'tenant_id'  => null,
            ];
            try {
                $this->dao->getModel()->withoutGlobalScope(TenantScope::class)->create($configData);
            } catch (\Exception $e) {
            }
            return $default;
        }

        $content = $configModel->getOriginal('content', null);
        if (is_string($content) && !empty($content)) {
            $decoded = json_decode($content, true);
            return json_last_error() === JSON_ERROR_NONE ? $decoded : $content;
        }
        return $content ?? $default;
    }

    /**
     * 更新/保存平台配置
     */
    public function update(string $code, mixed $content, array $options = []): void
    {
        $processedContent = $content;
        if (is_array($content)) {
            $processedContent = json_encode($content, JSON_UNESCAPED_UNICODE);
        }

        $model = $this->dao->getModel()
            ->withoutGlobalScope(TenantScope::class)
            ->where('code', $code)
            ->whereNull('tenant_id')
            ->first();

        if ($model) {
            $model->content = $processedContent;
            if (isset($options['group_code'])) {
                $model->group_code = $options['group_code'];
            }
            if (isset($options['name'])) {
                $model->name = $options['name'];
            }
            if (isset($options['enabled'])) {
                $model->enabled = (int)$options['enabled'];
            }
            $model->save();
        } else {
            $data = [
                'code'       => $code,
                'content'    => $processedContent,
                'name'       => $options['name'] ?? $code,
                'group_code' => $options['group_code'] ?? 'default',
                'enabled'    => isset($options['enabled']) ? (int)$options['enabled'] : 1,
                'tenant_id'  => null,
            ];
            $this->dao->getModel()->withoutGlobalScope(TenantScope::class)->create($data);
        }
    }

    /**
     * 获取平台站点信息（登录前使用）
     *
     * 查询 group_code='platform' + code='site_setting' 的单条配置，
     * 返回 content 字段 JSON 解码值。
     */
    public function getSiteInfo(array $default = []): array
    {
        $config = $this->dao->getModel()
            ->withoutGlobalScope(TenantScope::class)
            ->where('group_code', 'platform')
            ->where('code', 'site_setting')
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
     * 按分组获取平台配置
     */
    public function getByGroup(string $groupCode, array $defaults = [], array $options = []): array
    {
        if (empty($groupCode)) {
            return $defaults;
        }

        $enabledOnly  = $options['enabled_only'] ?? true;
        $withMetadata = $options['with_metadata'] ?? false;

        $query = $this->dao->getModel()
            ->withoutGlobalScope(TenantScope::class)
            ->where('group_code', $groupCode)
            ->whereNull('tenant_id');

        if ($enabledOnly) {
            $query->where('enabled', 1);
        }

        $configs = $query->get()->toArray();

        if ($withMetadata) {
            $processedData = [];
            foreach ($configs as $config) {
                $content = $config['content'] ?? null;
                if (is_string($content) && !empty($content)) {
                    $decoded = json_decode($content, true);
                    $config['content'] = json_last_error() === JSON_ERROR_NONE ? $decoded : $content;
                }
                if (isset($options['key_by']) && $options['key_by'] === 'code') {
                    $processedData[$config['code']] = $config;
                } else {
                    $processedData[] = $config;
                }
            }
            return $processedData;
        }

        $processedData = [];
        foreach ($configs as $config) {
            $content = $config['content'] ?? null;
            if (is_string($content) && !empty($content)) {
                $decoded = json_decode($content, true);
                $processedData[$config['code']] = json_last_error() === JSON_ERROR_NONE ? $decoded : $content;
            } else {
                $processedData[$config['code']] = $content;
            }
        }
        return array_merge($defaults, $processedData);
    }

    /**
     * 获取平台配置项列表（分页）
     */
    public function getItems(int $page, int $pageSize, string $groupCode, string $keyword = ''): array
    {
        $query = $this->dao->getModel()
            ->withoutGlobalScope(TenantScope::class)
            ->where('is_sys', '!=', 1)
            ->whereNull('tenant_id');

        if (!empty($keyword)) {
            $query->where('code', $keyword);
        }

        $total = $query->count();
        $list  = $query->orderBy('sort')->orderBy('id')
            ->skip(($page - 1) * $pageSize)
            ->take($pageSize)
            ->get()
            ->toArray();

        foreach ($list as &$item) {
            $item['value'] = $this->formatValue($item['content'] ?? '', $item['type'] ?? 'string');
        }

        return [
            'items' => $list,
            'total' => $total,
        ];
    }

    private function formatValue(mixed $content, string $type): mixed
    {
        return match ($type) {
            'string'  => (string)$content,
            'number'  => (float)$content,
            'boolean' => (bool)$content,
            'json'    => is_string($content) ? json_decode($content, true) : $content,
            default   => $content,
        };
    }
}
