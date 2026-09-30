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

use app\service\admin\system\MenuService;
use core\business\plugin\PluginPath;
use core\foundation\exception\handler\PluginException;

/**
 * 插件构建服务
 */
final class PluginBuildService extends PluginBaseService
{
    private ?string $plugin;
    private ?string $pluginPath;

    /**
     * 插件打包
     *
     * @param string $plugin 插件名称
     *
     * @return true
     * @throws PluginException
     * @throws \Exception
     */
    public function build(string $plugin): true
    {
        $this->plugin = $plugin;
        $this->pluginPath = PluginPath::pluginRoot($plugin) . DIRECTORY_SEPARATOR;

        if (!is_dir($this->pluginPath)) {
            throw new PluginException('目录中不存在此项插件');
        }

        // 执行各模块打包
        $this->packageAdmin();
        $this->packageUniapp();
        $this->buildUniappPagesJson();
        $this->buildUniappLangJson();
        $this->packageWeb();
        $this->packageResource();
        $this->syncMenu('admin');
        $this->syncMenu('site');

        // 清理临时文件并准备打包
        $this->cleanupAndPreparePackage();

        return true;
    }

    /**
     * 同步菜单
     *
     * @param string $appType 应用类型
     *
     * @return true
     */
    public function syncMenu(string $appType): true
    {

        $menuService = \support\Container::get(MenuService::class);
        $where = [
            ['app_type', '=', $appType],
            ['plugin', '=', $this->plugin]
        ];
        $fields = [
            'menu_name', 'menu_key', 'menu_short_name', 'parent_select_key',
            'parent_key', 'menu_type', 'icon', 'api_url', 'router_path',
            'view_path', 'methods', 'sort', 'status', 'is_show'
        ];

        $menus = $menuService->getModel()
            ->query()
            ->where($where)
            ->order('sort', 'desc')
            ->get()
            ->toArray();

        $menuTree = [];
        if (!empty($menus)) {
            $menuTree = $this->buildMenuTree($menus);
            $menuService->getModel()->query()->where($where)->update(['source' => 'system']);
        }

        $menuConfigPath = $this->pluginPath . 'resource' . DIRECTORY_SEPARATOR . 'menu' . DIRECTORY_SEPARATOR . 'menu' . DIRECTORY_SEPARATOR . $appType . '.php';

        $deleteMenuKeys = [];
        if (file_exists($menuConfigPath)) {
            $menuConfig = include $menuConfigPath;
            $deleteMenuKeys = $menuConfig['delete'] ?? [];
        }

        $content = $this->generateMenuConfigContent($menuTree, $deleteMenuKeys);
        file_put_contents($menuConfigPath, $content);

        return true;
    }

    /**
     * 构建菜单树
     *
     * @param array  $menuList 菜单列表
     * @param string $pk       主键字段
     * @param string $pid      父级ID字段
     * @param string $child    子节点字段
     * @param string $root     根节点值
     *
     * @return array
     */
    private function buildMenuTree(array $menuList, string $pk = 'menu_key', string $pid = 'parent_key', string $child = 'children', string $root = ''): array
    {
        $tree = [];
        if (empty($menuList)) {
            return $tree;
        }

        // 创建基于主键的数组引用
        $menuRefer = [];
        foreach ($menuList as $key => &$menu) {
            $menuRefer[$menu[$pk]] = &$menuList[$key];
        }

        foreach ($menuList as $key => &$menu) {
            $parentId = $menu[$pid];
            if ($root === $parentId) {
                $tree[] = &$menuList[$key];
            } else {
                if (isset($menuRefer[$parentId])) {
                    $parentMenu = &$menuRefer[$parentId];
                    $parentMenu[$child][] = &$menuList[$key];
                } else {
                    $tree[] = &$menuList[$key];
                }
            }
        }

        return $tree;
    }

    /**
     * 生成菜单配置内容
     *
     * @param array $menuTree       菜单树
     * @param array $deleteMenuKeys 要删除的菜单键
     *
     * @return string
     */
    private function generateMenuConfigContent(array $menuTree, array $deleteMenuKeys): string
    {
        $content = '<?php' . PHP_EOL;
        $content .= 'return [' . PHP_EOL;
        $content .= $this->formatArrayToPhp($menuTree);

        if (!empty($deleteMenuKeys)) {
            $deleteKeys = array_map(function ($key) {
                return "'{$key}'";
            }, $deleteMenuKeys);
            $content .= "    'delete' => [" . implode(',', $deleteKeys) . "]" . PHP_EOL;
        }

        $content .= '];';
        return $content;
    }

    /**
     * 格式化数组为PHP代码字符串
     *
     * @param array $array 要格式化的数组
     * @param int   $level 缩进级别
     *
     * @return string
     */
    private function formatArrayToPhp(array $array, int $level = 1): string
    {
        $indent = str_repeat('    ', $level);
        $content = '';

        foreach ($array as $key => $value) {
            // 跳过不需要的字段
            if (in_array($key, ['status_name', 'menu_type_name']) || ($level > 2 && $key == 'parent_key')) {
                continue;
            }

            $content .= $indent;
            if (is_string($key)) {
                $content .= var_export($key, true) . ' => ';
            }

            if (is_array($value)) {
                $content .= '[' . PHP_EOL . $this->formatArrayToPhp($value, $level + 1);
                $content .= $indent . '],' . PHP_EOL;
            } else {
                $content .= var_export((string) $value, true) . ',' . PHP_EOL;
            }
        }

        return $content;
    }

    /**
     * 打包Admin模块
     *
     * @return true
     */
    public function packageAdmin(): true
    {
        $sourcePath = PluginPath::templateProjectPath('mono/apps/admin') . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'plugin' . DIRECTORY_SEPARATOR . $this->plugin . DIRECTORY_SEPARATOR;
        if (!is_dir($sourcePath)) {
            return true;
        }

        $targetPath = $this->pluginPath . 'admin' . DIRECTORY_SEPARATOR;
        $this->copyDirectoryWithCleanup($sourcePath, $targetPath);

        // 打包admin icon文件
        $iconSourcePath = $this->getFrontendProjectPath('admin') . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'styles' . DIRECTORY_SEPARATOR . 'icon' . DIRECTORY_SEPARATOR . 'addon' . DIRECTORY_SEPARATOR . $this->plugin;
        $iconTargetPath = $targetPath . 'icon' . DIRECTORY_SEPARATOR;

        if (is_dir($iconSourcePath)) {
            $this->copyDirectoryWithCleanup($iconSourcePath, $iconTargetPath);
        }

        return true;
    }

    /**
     * 打包Uniapp模块
     *
     * @return true
     */
    public function packageUniapp(): true
    {
        $sourcePath = $this->getFrontendProjectPath('uni-app') . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'addon' . DIRECTORY_SEPARATOR . $this->plugin . DIRECTORY_SEPARATOR;
        if (!is_dir($sourcePath)) {
            return true;
        }

        $targetPath = $this->pluginPath . 'uni-app' . DIRECTORY_SEPARATOR;
        $this->copyDirectoryWithCleanup($sourcePath, $targetPath);

        return true;
    }

    /**
     * 构建uni-app页面配置
     *
     * @return true
     * @throws \Exception
     */
    public function buildUniappPagesJson(): true
    {
        $pagesJsonPath = $this->getFrontendProjectPath('uni-app') . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'pages.json';
        if (!file_exists($pagesJsonPath)) {
            return true;
        }

        $pagesJsonContent = file_get_contents($pagesJsonPath);
        $codeBegin = strtoupper($this->plugin) . '_PAGE_BEGIN' . PHP_EOL;
        $codeEnd = strtoupper($this->plugin) . '_PAGE_END' . PHP_EOL;

        if (!str_contains($pagesJsonContent, $codeBegin) || !str_contains($pagesJsonContent, $codeEnd)) {
            return true;
        }

        $pattern = "/\\/\\/\s+{$codeBegin}([\\S\\s]+)\\/\\/\s+{$codeEnd}?/";
        preg_match($pattern, $pagesJsonContent, $matches);

        if (empty($matches)) {
            return true;
        }

        $addonPages = str_replace(PHP_EOL . ',' . PHP_EOL, '', $matches[1]);
        $packagePath = $this->pluginPath . 'package';

        if (!is_dir($packagePath)) {
            create_directory($packagePath);
        }

        $content = '<?php' . PHP_EOL;
        $content .= 'return [' . PHP_EOL . "    'pages' => <<<EOT" . PHP_EOL . '        // PAGE_BEGIN' . PHP_EOL;
        $content .= $addonPages;
        $content .= '// PAGE_END' . PHP_EOL . 'EOT' . PHP_EOL . '];';

        file_put_contents($packagePath . DIRECTORY_SEPARATOR . 'uni-app-pages.php', $content);

        return true;
    }

    /**
     * 构建uni-app语言文件
     *
     * @return true
     * @throws \Exception
     */
    public function buildUniappLangJson(): true
    {
        $zhJsonPath = $this->getFrontendProjectPath('uni-app') . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'locale' . DIRECTORY_SEPARATOR . 'zh-Hans.json';
        $enJsonPath = $this->getFrontendProjectPath('uni-app') . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'locale' . DIRECTORY_SEPARATOR . 'en.json';

        if (!file_exists($zhJsonPath) || !file_exists($enJsonPath)) {
            return true;
        }

        $zhJson = json_decode(file_get_contents($zhJsonPath), true);
        $enJson = json_decode(file_get_contents($enJsonPath), true);

        $pluginZh = [];
        $pluginEn = [];
        $pluginKeyPrefix = $this->plugin . '.';

        foreach ($zhJson as $key => $value) {
            if (str_starts_with($key, $pluginKeyPrefix)) {
                $newKey = str_replace($pluginKeyPrefix, '', $key);
                $pluginZh[$newKey] = $value;
            }
        }

        foreach ($enJson as $key => $value) {
            if (str_starts_with($key, $pluginKeyPrefix)) {
                $newKey = str_replace($pluginKeyPrefix, '', $key);
                $pluginEn[$newKey] = $value;
            }
        }

        $langDir = $this->pluginPath . 'uni-app' . DIRECTORY_SEPARATOR . 'locale' . DIRECTORY_SEPARATOR;
        if (!is_dir($langDir)) {
            create_directory($langDir);
        }

        $this->writeJsonFile($pluginZh, $langDir . 'zh-Hans.json');
        $this->writeJsonFile($pluginEn, $langDir . 'en.json');

        return true;
    }

    /**
     * 打包Web模块
     *
     * @return true
     */
    public function packageWeb(): true
    {
        $sourcePath = PluginPath::templateProjectPath('web') . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'plugin' . DIRECTORY_SEPARATOR . $this->plugin . DIRECTORY_SEPARATOR;
        if (!is_dir($sourcePath)) {
            return true;
        }

        $targetPath = $this->pluginPath . 'web' . DIRECTORY_SEPARATOR;
        $this->copyDirectoryWithCleanup($sourcePath, $targetPath);

        // 处理布局文件
        $layoutSourcePath = $this->getFrontendProjectPath('web') . DIRECTORY_SEPARATOR . 'layouts' . DIRECTORY_SEPARATOR . $this->plugin;
        if (is_dir($layoutSourcePath)) {
            $layoutTargetPath = $targetPath . 'layouts' . DIRECTORY_SEPARATOR . $this->plugin;
            $this->copyDirectoryWithCleanup($layoutSourcePath, $layoutTargetPath);
        }

        return true;
    }

    /**
     * 打包资源文件
     *
     * @return true
     */
    public function packageResource(): true
    {
        $sourcePath = public_path() . 'plugin' . DIRECTORY_SEPARATOR . $this->plugin . DIRECTORY_SEPARATOR;
        if (!is_dir($sourcePath)) {
            return true;
        }

        $targetPath = $this->pluginPath . 'resource' . DIRECTORY_SEPARATOR;
        $this->copyDirectoryWithCleanup($sourcePath, $targetPath);

        return true;
    }

    /**
     * 清理临时文件并准备打包
     *
     * @return void
     * @throws \Exception
     */
    private function cleanupAndPreparePackage(): void
    {
        // TODO: 当前方法体为空，打包流程扩展到需要临时目录时可在此实现
        // 预期用途：拷贝插件文件到临时目录 → 创建 zip 包 → 清理临时文件
        return;
    }

    /**
     * 复制目录并清理目标目录
     *
     * @param string $sourcePath 源目录路径
     * @param string $targetPath 目标目录路径
     *
     * @return void
     */
    private function copyDirectoryWithCleanup(string $sourcePath, string $targetPath): void
    {
        if (is_dir($targetPath)) {
            remove_directory($targetPath, true);
        }
        copy_directory($sourcePath, $targetPath);
    }
}