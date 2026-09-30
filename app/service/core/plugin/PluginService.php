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
namespace app\service\core\plugin;

use app\dao\plugin\PluginDao;
use support\Container;

/**
 * 插件服务类
 *
 * @package app\service\core\plugin
 */
final class PluginService extends PluginBaseService
{
    public function __construct(PluginDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 环境检测
     */
    public function checkEnvironment(string $code): array
    {
        /** @var PluginInstallService $installService */
        $installService = Container::make(PluginInstallService::class);
        $result = $installService->installCheck($code);

        $paths = [];
        foreach ($result['checks'] ?? [] as $check) {
            $requirement = match ($check['permission_type'] ?? $check['type'] ?? '') {
                'writable' => 'writable',
                'readable' => 'readable',
                default    => 'readable',
            };
            $paths[] = [
                'path'        => $check['path'] ?? $check['name'] ?? '',
                'requirement' => $requirement,
                'status'      => $check['status'] ?? 'error',
            ];
        }

        return compact('paths');
    }

    /**
     * 获取插件列表（委托给 PluginListService）
     */
    public function getList(string|null $category = 'all', string|null $type = null, string|null $keyword = null, int $page = 1, int $limit = 9999): array
    {
        return Container::get(PluginListService::class)->getList($category, $type, $keyword, $page, $limit);
    }

    /**
     * 获取本地插件列表（从 plugin 目录扫描）
     */
    public function getLocalModules(string $authCode = ''): array
    {
        $modules    = [];
        $pluginPath = base_path('plugin');

        if (!is_dir($pluginPath)) {
            return $modules;
        }

        $dirItems = scandir($pluginPath);
        foreach ($dirItems as $key => $item) {
            if ($item === '.' || $item === '..' || !is_dir($pluginPath . DIRECTORY_SEPARATOR . $item)) {
                continue;
            }

            $itemPath      = $pluginPath . DIRECTORY_SEPARATOR . $item;
            $infoPath      = $itemPath . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'info.php';
            $installedPath = $itemPath . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'installed.php';
            $publicPath    = $itemPath . DIRECTORY_SEPARATOR . 'public';

            if (!file_exists($infoPath)) {
                continue;
            }

            $config = include $infoPath;
            if (!is_array($config)) {
                continue;
            }

            $pluginType = $config['type'] ?? 'madong';
            if (!str_starts_with($pluginType, 'madong:') && $pluginType !== 'madong') {
                continue;
            }

            $isInstalled = file_exists($installedPath);
            $installedAt = 0;
            if ($isInstalled) {
                $installedConfig = include $installedPath;
                if (is_array($installedConfig) && !empty($installedConfig['installed_at'])) {
                    $installedAt = strtotime($installedConfig['installed_at']);
                } else {
                    $installedAt = time();
                }
            }

            $icon  = '';
            $cover = '';
            if (is_dir($publicPath)) {
                if (file_exists($publicPath . '/icon.png')) {
                    $icon = base64_encode(file_get_contents($publicPath . '/icon.png'));
                }
                if (file_exists($publicPath . '/cover.png')) {
                    $cover = base64_encode(file_get_contents($publicPath . '/cover.png'));
                }
            }

            $modules[] = [
                'id'           => $key,
                'code'         => $config['name'] ?? $item,
                'name'         => $config['name'] ?? $item,
                'type'         => $config['type'] ?? 'madong',
                'version'      => $config['version'] ?? '1.0.0',
                'status'       => $isInstalled ? 1 : 0,
                'description'  => $config['description'] ?? '',
                'author'       => $config['author'] ?? '',
                'price'        => 0,
                'cover'        => $cover,
                'icon'         => $icon,
                'downloads'    => 0,
                'rating'       => 0,
                'created_at'   => date('Y-m-d H:i:s'),
                'updated_at'   => date('Y-m-d H:i:s'),
                'auth_code'    => $authCode,
                'is_installed' => $isInstalled,
                'installed_at' => $installedAt,
                'is_local'     => 1,
                'undeletable'  => isset($config['uninstall']['undeletable']) && $config['uninstall']['undeletable'] === true ? 1 : 0,
            ];
        }

        return $modules;
    }

    /**
     * 读取插件 info 配置 (供 PluginTenantAuthController::dependencies 使用)
     */
    public function getConfig(string $key): ?array
    {
        $infoPath = base_path('plugin' . DIRECTORY_SEPARATOR . $key . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'info.php');
        if (!file_exists($infoPath)) {
            return null;
        }
        $cfg = include $infoPath;
        return is_array($cfg) ? $cfg : null;
    }

    /**
     * 判断插件是否已装 (installed.php 存在)
     */
    public function isInstalled(string $key): bool
    {
        $installedPath = base_path('plugin' . DIRECTORY_SEPARATOR . $key . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'installed.php');
        return file_exists($installedPath);
    }

    /**
     * 获取插件下拉选择列表
     */
    public function getSelectList(string $keyword = ''): array
    {
        $modules = $this->getLocalModules();
        $list = [];
        foreach ($modules as $module) {
            if ($keyword && !str_contains(strtolower($module['name']), strtolower($keyword))) {
                continue;
            }
            $list[] = [
                'label' => $module['name'],
                'value' => $module['name'],
            ];
        }
        return $list;
    }

    /**
     * 获取模块升级日志
     */
    public function getUpgradeLogs(string $moduleName): array
    {
        try {
            $config = [
                'auth_code'   => config('madong.auth_code', ''),
                'auth_secret' => config('madong.auth_secret', ''),
                'market_host' => config('madong.market_host', 'https://madong.tech'),
            ];

            /** @var PluginRemoteService $pluginRemoteService */
            $pluginRemoteService = Container::make(PluginRemoteService::class);
            return $pluginRemoteService->getRemoteUpdateLogs(
                $config['auth_code'],
                $config['auth_secret'],
                $moduleName
            );
        } catch (\Throwable $e) {
            return [];
        }
    }
}