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

namespace core\communication\mcp\tool\system;

use core\communication\mcp\attribute\McpTool;
use core\communication\mcp\security\McpUser;

/**
 * config_read：系统配置读取（权限码 mcp:config:read）
 *
 * 安全约束：
 *  - 必须传 key（如 database、cache.custom），不允许全量 config()
 *  - 敏感项（password/passwd/secret/token/key/credential/apikey）自动脱敏为 ***
 */
final class ConfigReadTool
{
    /** 命中这些关键字的值会被脱敏 */
    private const SENSITIVE_KEYWORDS = [
        'password', 'passwd', 'secret', 'token', 'credential',
        'apikey', 'api_key', 'access_key', 'private_key', 'app_secret',
    ];

    public function __construct(
        private readonly ?McpUser $user = null,
    ) {
    }

    #[McpTool(
        name: 'config_read',
        title: 'Config Read',
        description: '读取系统配置（config() 的任意 key，如 database / cache.custom / mcp）。必须指定 key，不允许全量导出。敏感字段（password/secret/token/key 等）值自动脱敏为 ***。需要权限码 mcp:config:read。',
        inputSchema: [
            'type'       => 'object',
            'properties' => [
                'key' => [
                    'type'        => 'string',
                    'description' => '配置键名，支持点号分隔的嵌套（如 database / cache.custom / mcp.auth）',
                ],
            ],
            'required' => ['key'],
        ],
        permission: 'mcp:config:read',
    )]
    public function read(string $key): array
    {
        $key = trim($key);
        if ($key === '') {
            return ['found' => false, 'message' => 'key 不能为空'];
        }
        // 禁止顶层全量导出
        if (!str_contains($key, '.')) {
            return ['found' => false, 'message' => '不允许读取顶层全量配置（如 config("database")），请指定子键如 database.default'];
        }

        $value = config($key);
        if ($value === null) {
            return ['found' => false, 'message' => "配置键 '{$key}' 不存在"];
        }

        return [
            'found'    => true,
            'key'      => $key,
            'value'    => $this->sanitize($key, $value),
        ];
    }

    /**
     * 递归脱敏
     */
    private function sanitize(string $keyPath, mixed $value): mixed
    {
        if (is_array($value)) {
            foreach ($value as $k => $v) {
                $value[$k] = $this->sanitize($keyPath . '.' . $k, $v);
            }
            return $value;
        }

        if (is_string($value) && $this->isSensitive($keyPath)) {
            return '***';
        }

        return $value;
    }

    private function isSensitive(string $keyPath): bool
    {
        $lower = strtolower($keyPath);
        foreach (self::SENSITIVE_KEYWORDS as $kw) {
            if (str_contains($lower, $kw)) {
                return true;
            }
        }
        return false;
    }
}
