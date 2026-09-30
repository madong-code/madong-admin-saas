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

namespace core\io\upload\support;

use core\business\tenant\context\TenantContext;

/**
 * 存储路径解析器
 *
 * 统一本地目录与云 object key 的租户前缀规则：
 * - 配置含 tenant_path → 以配置为准
 * - 配置缺 key 时：租户模式默认注入 tenant_{id}；单体 / 平台 / 无租户上下文不注入
 */
class StoragePathResolver
{
    public const DEFAULT_PATTERN = 'tenant_{tenant_id}';

    /**
     * 解析租户路径段
     *
     * @param array $config 存储驱动配置
     *
     * @return string 空字符串或 tenant_{id}
     */
    public function tenantSegment(array $config = []): string
    {
        if (!$this->shouldIsolate($config)) {
            return '';
        }

        $tenantId = TenantContext::getTenantId();
        if ($tenantId === null || $tenantId === '') {
            return '';
        }

        $pattern = (string)($config['tenant_path_pattern'] ?? self::DEFAULT_PATTERN);
        if ($pattern === '') {
            $pattern = self::DEFAULT_PATTERN;
        }

        return str_replace(
            ['{tenant_id}', '{tenantId}'],
            [(string)$tenantId, (string)$tenantId],
            $pattern
        );
    }

    /**
     * 解析业务子目录（含租户前缀）
     *
     * Local 使用：tenant_{id}/{biz_sub}
     * 无业务子目录时仅返回租户段
     *
     * @param string $bizSub  业务子目录（如 image、202607）
     * @param array  $config  存储配置
     * @param array  $options 上传 options（可含 sub_dir）
     *
     * @return string
     */
    public function resolveSubdir(string $bizSub = '', array $config = [], array $options = []): string
    {
        if ($bizSub === '' && isset($options['sub_dir'])) {
            $bizSub = (string)$options['sub_dir'];
        }
        if ($bizSub === '' && isset($config['sub_dir'])) {
            $bizSub = is_callable($config['sub_dir'])
                ? (string)$config['sub_dir']()
                : (string)$config['sub_dir'];
        }

        $tenant = $this->tenantSegment($config);

        return $this->joinPaths($tenant, $bizSub);
    }

    /**
     * 构建对象存储 key / 相对路径
     *
     * 规则：{dirname}/{tenant_segment}/{biz_sub}/{filename}
     * 单体时 tenant_segment 为空
     *
     * @param string $dirname  根目录名（如 upload）
     * @param string $filename 文件名
     * @param array  $config   存储配置
     * @param array  $options  上传 options
     *
     * @return string
     */
    public function buildObjectKey(
        string $dirname,
        string $filename,
        array $config = [],
        array $options = []
    ): string {
        $bizSub = '';
        if (isset($options['sub_dir']) && (string)$options['sub_dir'] !== '') {
            $bizSub = (string)$options['sub_dir'];
        } elseif (isset($config['sub_dir']) && $config['sub_dir'] !== '' && $config['sub_dir'] !== null) {
            $bizSub = is_callable($config['sub_dir'])
                ? (string)$config['sub_dir']()
                : (string)$config['sub_dir'];
        }

        $tenant = $this->tenantSegment($config);

        return $this->joinPaths($dirname, $tenant, $bizSub, $filename);
    }

    /**
     * 是否启用租户路径隔离
     *
     * 规则（兼容旧配置，缺 key 时走场景默认）：
     * 1. 单体模式 / 租户未启用 → 不隔离
     * 2. 租户上下文未初始化或无 tenant_id（含平台无租户）→ 不隔离
     * 3. 配置含 tenant_path → 以配置值为准
     * 4. 配置不含 tenant_path → 租户模式默认开启前缀隔离
     */
    public function shouldIsolate(array $config = []): bool
    {
        // 单体 / 未启用：永不隔离
        if (TenantContext::isSingleMode() || !TenantContext::isTenantEnabled()) {
            return false;
        }

        // 无租户上下文：无法隔离（平台、未登录租户等）
        if (!TenantContext::isInitialized()) {
            return false;
        }

        $tenantId = TenantContext::getTenantId();
        if ($tenantId === null || $tenantId === '') {
            return false;
        }

        // 配置显式指定时以配置为准（兼容 true/false）
        if (array_key_exists('tenant_path', $config)) {
            return (bool)$config['tenant_path'];
        }

        // 缺省：租户模式默认带前缀
        return true;
    }

    /**
     * 拼接路径段，去除空段与重复斜杠
     */
    public function joinPaths(string ...$segments): string
    {
        $parts = [];
        foreach ($segments as $segment) {
            $segment = str_replace('\\', '/', (string)$segment);
            $segment = trim($segment, '/');
            if ($segment === '') {
                continue;
            }
            $parts[] = $segment;
        }

        return implode('/', $parts);
    }
}
