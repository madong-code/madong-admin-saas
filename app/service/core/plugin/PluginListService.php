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

use support\Container;

/**
 * 插件列表服务
 *
 * 负责插件的列表查询、过滤、排序等逻辑
 * 从 PluginService 中拆分出来的列表专用服务
 *
 * @package app\service\core\plugin
 */
class PluginListService
{
    /** 免费插件白名单 */
    private const FREE_PLUGINS = ['sms-verification', 'wechat-integration'];

    public function __construct(
        private PluginService $pluginService,
        private PluginRemoteService $pluginRemoteService,
    ) {}

    /**
     * 获取插件列表
     *
     * @param string|null $category 插件分类（all/installed/un_installed/purchased/updatable）
     * @param string|null $type     插件类型
     * @param string|null $keyword  搜索关键词
     * @param int         $page     页码
     * @param int         $limit    每页数量
     *
     * @return array 插件列表
     */
    public function getList(string|null $category = 'all', string|null $type = null, string|null $keyword = null, int $page = 1, int $limit = 9999): array
    {
        // 获取授权配置
        $config = [
            'auth_code'   => config('madong.auth_code', ''),
            'auth_secret' => config('madong.auth_secret', ''),
            'page'        => $page,
            'limit'       => $limit,
            'market_host' => config('madong.market_host', 'https://madong.tech'),
            'name'        => $keyword,
        ];

        // 获取本地插件列表
        $localModules = $this->pluginService->getLocalModules($config['auth_code']);

        // 获取已购买的插件列表
        $purchasedModules = $this->pluginRemoteService->getPurchasedModules($config);

        // 从本地插件中派生出已安装的模块
        $installedModules = array_filter($localModules, function ($module) {
            return $module['is_installed'] === true;
        });
        // 构建已安装插件的映射
        $installedMap = array_column($installedModules, null, 'name');

        // 合并本地和远程市场的插件
        $allModules = $this->mergeModules($localModules, $purchasedModules);

        // 根据不同类型处理数据
        $processedItems = match ($category) {
            'installed'    => $this->getInstalledModulesData($allModules, $installedMap, $installedModules),
            'un_installed' => $this->getUninstalledModulesData($localModules, $installedMap),
            'purchased'    => $this->getPurchasedModulesData($purchasedModules, $installedMap),
            'updatable'    => $this->getUpdatableModulesData($allModules, $installedMap, $installedModules),
            default        => $this->getAllModulesData($allModules, $installedMap),
        };

        // 应用通用过滤
        $filteredItems = [];
        foreach ($processedItems as $module) {
            if ($type !== null && (string)$module['status'] !== (string)$type) {
                continue;
            }
            if ($keyword && !$this->matchKeyword($module, $keyword)) {
                continue;
            }
            $filteredItems[] = $module;
        }

        // 分页处理
        $total = count($filteredItems);
        $items = array_slice($filteredItems, ($page - 1) * $limit, $limit);

        return compact('page', 'limit', 'total', 'items');
    }

    /**
     * 合并本地和远程市场的插件
     */
    private function mergeModules(array $localModules, array $purchasedModules): array
    {
        $merged    = [];
        $moduleMap = [];

        // 先添加本地插件
        foreach ($localModules as $module) {
            $merged[]                   = $module;
            $moduleMap[$module['name']] = count($merged) - 1;
        }

        // 再添加远程市场的插件（如果本地没有）
        foreach ($purchasedModules as $module) {
            if (!isset($moduleMap[$module['name']])) {
                $merged[] = $module;
            }
        }

        return $merged;
    }

    /**
     * 获取已安装模块数据
     */
    private function getInstalledModulesData(array $allModules, array $installedMap, array $installedModules): array
    {
        $result = [];
        foreach ($installedModules as $localModule) {
            $enable = $localModule['enable'] ?? true;
            if (!$enable) {
                continue;
            }

            $name         = $localModule['name'];
            $remoteModule = $this->findModuleByName($allModules, $name);

            $result[] = $this->buildModuleItem(
                $remoteModule ?: $this->createFallbackModule($localModule),
                $localModule,
                $installedMap
            );
        }
        return $result;
    }

    /**
     * 获取未安装模块数据
     */
    private function getUninstalledModulesData(array $localModules, array $installedMap): array
    {
        $result = [];
        foreach ($localModules as $module) {
            if (isset($installedMap[$module['name']])) {
                continue;
            }
            $result[] = $this->buildModuleItem($module, null, $installedMap);
        }
        return $result;
    }

    /**
     * 获取已购买模块数据
     */
    private function getPurchasedModulesData(array $purchasedModules, array $installedMap): array
    {
        $result = [];
        foreach ($purchasedModules as $module) {
            $localModule = $installedMap[$module['name']] ?? null;
            $result[]    = $this->buildModuleItem($module, $localModule, $installedMap);
        }
        return $result;
    }

    /**
     * 获取可更新模块数据
     */
    private function getUpdatableModulesData(array $allModules, array $installedMap, array $installedModules): array
    {
        $result = [];
        foreach ($installedModules as $localModule) {
            $enable = $localModule['enable'] ?? true;
            if (!$enable) {
                continue;
            }

            $name         = $localModule['name'];
            $remoteModule = $this->findModuleByName($allModules, $name);

            if (!$remoteModule) {
                continue;
            }

            if (version_compare($remoteModule['version'], $localModule['version'], '>')) {
                $result[] = $this->buildModuleItem($remoteModule, $localModule, $installedMap);
            }
        }
        return $result;
    }

    /**
     * 获取所有模块数据
     */
    private function getAllModulesData(array $allModules, array $installedMap): array
    {
        $result = [];
        foreach ($allModules as $module) {
            $localModule = $installedMap[$module['name']] ?? null;
            $result[]    = $this->buildModuleItem($module, $localModule, $installedMap);
        }
        return $result;
    }

    /**
     * 构建模块数据项（包含安装状态、版本差异等）
     */
    private function buildModuleItem(?array $remoteModule, ?array $localModule, array $installedMap): array
    {
        $name          = $remoteModule['name'] ?? $localModule['name'] ?? '';
        $isInstalled   = $localModule !== null;
        $localVersion  = $isInstalled ? $localModule['version'] : null;
        $remoteVersion = $remoteModule['version'] ?? null;

        // 获取本地插件标志
        $isLocal = ($localModule && isset($localModule['is_local'])) ? $localModule['is_local'] : 0;

        // 获取不可删除标志（优先从 localModule 获取）
        $undeletable = 0;
        if ($localModule && isset($localModule['undeletable'])) {
            $undeletable = $localModule['undeletable'];
        }

        // 计算可更新状态
        $hasUpdate         = false;
        $versionComparison = null;
        if ($isInstalled && $remoteVersion) {
            $hasUpdate         = version_compare($remoteVersion, $localVersion, '>');
            $versionComparison = $hasUpdate ? "{$remoteVersion} > {$localVersion}" : null;
        }

        // 计算购买状态
        $isPurchased = $remoteModule
            ? ($remoteModule['price'] == 0 || in_array($remoteModule['name'], self::FREE_PLUGINS))
            : false;

        return [
            'id'                    => $remoteModule['id'] ?? $localModule['name'] ?? '',
            'code'                  => $remoteModule['code'] ?? $localModule['name'] ?? '',
            'name'                  => $name,
            'type'                  => $remoteModule['type'] ?? ($localModule['type'] ?? 'module'),
            'version'               => $remoteVersion ?? $localVersion ?? '',
            'status'                => $remoteModule['status'] ?? 1,
            'description'           => $remoteModule['description'] ?? ($localModule['description'] ?? ''),
            'detail_description'    => $remoteModule['detail_description'] ?? '',
            'author'                => $remoteModule['author'] ?? ($localModule['author'] ?? ''),
            'cover'                 => $remoteModule['cover'] ?? '',
            'poster'                => $remoteModule['poster'] ?? '',
            'price'                 => $remoteModule['price'] ?? 0,
            'downloads'             => $remoteModule['downloads'] ?? 0,
            'rating'                => $remoteModule['rating'] ?? 0,
            'created_at'            => $remoteModule['created_at'] ?? date('Y-m-d H:i:s'),
            'updated_at'            => $remoteModule['updated_at'] ?? date('Y-m-d H:i:s'),
            'update_time'           => $remoteModule['update_time'] ?? '',
            'update_logs'           => $remoteModule['update_logs'] ?? [],
            'category'              => $remoteModule['category'] ?? null,
            'category_name'         => $remoteModule['category_name'] ?? '',
            'tags'                  => $remoteModule['tags'] ?? [],
            'is_new'                => (int)($remoteModule['is_new'] ?? 0),
            'is_hot'                => (int)($remoteModule['is_hot'] ?? 0),
            'purchased'             => (int)($remoteModule['purchased'] ?? 0),
            'installed'             => (int)($remoteModule['installed'] ?? 0),
            'manual_uninstall'      => (int)($remoteModule['manual_uninstall'] ?? 0),
            'composer_dependencies' => $remoteModule['composer_dependencies'] ?? [],
            'npm_dependencies'      => $remoteModule['npm_dependencies'] ?? [],

            // 状态标识 - 使用更专业的命名
            'is_installed'          => (int)$isInstalled,
            'installed_version'     => $localVersion,
            'has_update'            => (int)$hasUpdate,
            'is_purchased'          => (int)$isPurchased,
            'is_downloaded'         => (int)($isInstalled || in_array($name, ['data-export'])),
            'can_uninstall'         => (int)($isInstalled && $name !== 'user-management'),
            'can_download'          => (int)$isPurchased,
            'is_local'              => (int)$isLocal,
            'undeletable'           => $undeletable,

            // 专业化的版本信息结构
            'version_info'          => [
                'remote'     => [
                    'version'      => $remoteVersion,
                    'release_date' => $remoteModule['updated_at'] ?? null,
                ],
                'local'      => [
                    'version'      => $localVersion,
                    'install_date' => $localModule['created_at'] ?? null,
                ],
                'comparison' => [
                    'needs_update'       => (int)$hasUpdate,
                    'is_latest'          => (int)($isInstalled && $remoteVersion && version_compare($remoteVersion, $localVersion, '<=')),
                    'version_difference' => $versionComparison,
                ],
            ]
        ];
    }

    /**
     * 通过名称查找模块
     */
    private function findModuleByName(array $modules, string $name): ?array
    {
        foreach ($modules as $module) {
            if ($module['name'] === $name) {
                return $module;
            }
        }
        return null;
    }

    /**
     * 创建回退模块（当远程模块不存在时使用）
     */
    private function createFallbackModule(array $localModule): array
    {
        return [
            'name'        => $localModule['name'],
            'type'        => 'module',
            'version'     => $localModule['version'],
            'status'      => 1,
            'description' => $localModule['description'] ?? '',
            'author'      => $localModule['author'] ?? '',
            'price'       => 0,
            'cover'       => '',
            'downloads'   => 0,
            'rating'      => 0,
            'created_at'  => date('Y-m-d H:i:s'),
            'updated_at'  => date('Y-m-d H:i:s'),
            'undeletable' => $localModule['undeletable'] ?? 0,
        ];
    }

    /**
     * 匹配模块名称、描述和作者是否包含关键词
     */
    private function matchKeyword(array $module, string $keyword): bool
    {
        $lowerKeyword = strtolower($keyword);
        return str_contains(strtolower($module['name']), $lowerKeyword)
            || str_contains(strtolower($module['description']), $lowerKeyword)
            || str_contains(strtolower($module['author']), $lowerKeyword);
    }
}
