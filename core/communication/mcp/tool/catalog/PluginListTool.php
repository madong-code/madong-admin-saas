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

namespace core\communication\mcp\tool\catalog;

use core\communication\mcp\attribute\McpTool;
use core\communication\mcp\security\McpUser;

/**
 * plugin_list：本地插件清单（文件系统扫描，不依赖 app）
 */
final class PluginListTool
{
    public function __construct(
        private readonly ?McpUser $user = null,
    ) {
    }

    #[McpTool(
        name: 'plugin_list',
        title: 'Plugin List',
        description: '列出 madong 本地插件清单（编码/名称/版本/描述/作者/安装状态），扫描 plugin/ 目录的 config/info.php。',
        inputSchema: [
            'type' => 'object',
            'properties' => [
                'keyword' => [
                    'type' => 'string',
                    'description' => '按插件编码/名称/描述过滤（空=全部）',
                ],
                'installed_only' => [
                    'type' => 'boolean',
                    'default' => false,
                    'description' => 'true=仅返回已安装插件',
                ],
            ],
        ],
        permission: false,
    )]
    public function pluginList(string $keyword = '', bool $installedOnly = false): array
    {
        $keyword = trim($keyword);
        $pluginPath = base_path('plugin');
        $plugins = [];

        if (is_dir($pluginPath)) {
            foreach (scandir($pluginPath) ?: [] as $item) {
                if ($item === '.' || $item === '..' || !is_dir($pluginPath . DIRECTORY_SEPARATOR . $item)) {
                    continue;
                }

                $infoPath = $pluginPath . DIRECTORY_SEPARATOR . $item . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'info.php';
                if (!is_file($infoPath)) {
                    continue;
                }
                $info = include $infoPath;
                if (!is_array($info)) {
                    continue;
                }
                $type = (string) ($info['type'] ?? 'madong');
                if ($type !== 'madong' && !str_starts_with($type, 'madong:')) {
                    continue;
                }

                $installed = is_file($pluginPath . DIRECTORY_SEPARATOR . $item . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'installed.php');
                if ($installedOnly && !$installed) {
                    continue;
                }

                $code = (string) ($info['name'] ?? $item);
                $name = (string) ($info['name'] ?? $item);
                $description = (string) ($info['description'] ?? '');
                if ($keyword !== ''
                    && stripos($code, $keyword) === false
                    && stripos($name, $keyword) === false
                    && stripos($description, $keyword) === false) {
                    continue;
                }

                $plugins[] = [
                    'code'        => $code,
                    'name'        => $name,
                    'type'        => $type,
                    'version'     => (string) ($info['version'] ?? '1.0.0'),
                    'description' => $description,
                    'author'      => (string) ($info['author'] ?? ''),
                    'installed'   => $installed,
                ];
            }
        }

        usort($plugins, static fn (array $a, array $b): int => strcmp($a['code'], $b['code']));
        return ['total' => count($plugins), 'plugins' => $plugins];
    }
}
