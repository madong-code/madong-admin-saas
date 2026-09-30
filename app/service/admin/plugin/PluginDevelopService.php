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
 * Official Website: https://madong.tech
 */

namespace app\service\admin\plugin;

use app\dao\plugin\PluginDao;
use app\model\plugin\Plugin;
use app\process\Monitor;
use core\foundation\base\BaseService;
use core\foundation\exception\handler\AdminException;
use core\io\uuid\Snowflake;
use support\Container;
use ZipArchive;

/**
 * Plugin服务层 - 插件开发管理
 *
 * @author Mr.April
 * @since  1.0
 */
class PluginDevelopService extends BaseService
{
    private string $pluginDir;
    private string $adminDir;
    private string $webDir;

    public function __construct(PluginDao $dao)
    {
        $this->dao        = $dao;
        $basePath         = base_path();
        $projectPath      = dirname($basePath);

        $this->pluginDir  = $basePath . '/plugin';
        $this->adminDir   = $projectPath . '/template/mono/apps/admin/src/plugin';
        $this->webDir     = $projectPath . '/template/web/app/plugin';
    }

    /**
     * 删除插件（同时删除数据库记录和插件目录，防止 syncPlugins 重新创建）
     * 已安装的插件不允许删除，必须先卸载（防止前端分散资源残留）
     */
    public function destroyPlugin(int|string $id): void
    {
        $plugin = $this->dao->get($id);
        if (!$plugin) {
            throw new AdminException('插件不存在');
        }

        // 检查插件是否已安装 — 已安装的必须先卸载才能删除
        $installedConfigPath = $this->pluginDir . DIRECTORY_SEPARATOR . $plugin->key . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'installed.php';
        if (is_file($installedConfigPath)) {
            throw new AdminException('插件已安装，请先卸载后再删除');
        }

        // 先删除插件目录
        $pluginDir = $this->pluginDir . DIRECTORY_SEPARATOR . $plugin->key;
        if (is_dir($pluginDir)) {
            $this->removeDir($pluginDir);
        }

        // 再删除数据库记录
        $plugin->delete();
    }

    /**
     * 递归删除目录
     */
    private function removeDir(string $dir): void
    {
        $files = scandir($dir);
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }
            $path = $dir . DIRECTORY_SEPARATOR . $file;
            if (is_dir($path)) {
                $this->removeDir($path);
            } else {
                unlink($path);
            }
        }
        rmdir($dir);
    }

    /**
     * 获取插件列表（扫描插件目录并同步到数据库）
     */
    public function getList(array $where = [], int $page = 1, int $limit = 15): array
    {
        $this->syncPlugins();

        return $this->dao->getList($where, $page, $limit);
    }

    /**
     * 获取插件详情
     */
    public function show(int|string $id): ?Plugin
    {
        return $this->dao->get($id);
    }

    /**
     * 创建插件
     */
    public function store(array $data): mixed
    {
        $pluginKey = $data['key'] ?? '';
        $pluginDir = $this->pluginDir . DIRECTORY_SEPARATOR . $pluginKey;

        if (is_dir($pluginDir)) {
            throw new AdminException('插件目录已存在');
        }

        // 暂停文件监控，防止创建目录时触发重载
        $monitorSupportPause = method_exists(Monitor::class, 'pause');
        if ($monitorSupportPause) {
            Monitor::pause();
        }

        try {
            // 创建插件目录结构
            $this->createPluginStructure($pluginKey, $data);
        } finally {
            if ($monitorSupportPause) {
                Monitor::resume();
            }
        }

        $insertData = [
            'id'         => Snowflake::generate(),
            'key'        => $pluginKey,
            'title'      => $data['title'] ?? '',
            'desc'       => $data['desc'] ?? '',
            'author'     => $data['author'] ?? '',
            'version'    => $data['version'] ?? '1.0.0',
            'type'       => $data['type'] ?? 'custom',
            'status'     => 1,
            'icon'       => $data['icon'] ?? '',
            'cover'      => $data['cover'] ?? '',
            'support_app' => $data['support_app'] ?? '',
        ];

        return $this->dao->save($insertData);
    }

    /**
     * 更新插件
     */
    public function update(int|string $id, array $data): mixed
    {
        $plugin = $this->dao->get($id);
        if (!$plugin) {
            throw new AdminException('插件不存在');
        }

        // 写入info.php
        $pluginDir = $this->pluginDir . DIRECTORY_SEPARATOR . $plugin->key;
        if (is_dir($pluginDir)) {
            $this->writePluginInfo($pluginDir, $plugin->key, $data);
        }

        return $this->dao->update($id, $data);
    }

    /**
     * 打包插件
     */
    public function buildPlugin(int|string $id): array
    {
        $plugin = $this->dao->get($id);
        if (!$plugin) {
            throw new AdminException('插件不存在');
        }

        $pluginKey = $plugin->key;
        $pluginSourceDir = $this->pluginDir . DIRECTORY_SEPARATOR . $pluginKey;

        if (!is_dir($pluginSourceDir)) {
            throw new AdminException('插件目录不存在');
        }

        // 1. 执行 PluginBuildService 装配（复制前端文件等）
        try {
            $buildService = Container::make(\app\service\core\plugin\PluginBuildService::class);
            $buildService->build($pluginKey);
        } catch (\Throwable) {
            // 装配阶段失败不影响打包
        }

        // 2. 创建ZIP文件
        $zipFilePath = runtime_path() . DIRECTORY_SEPARATOR . $pluginKey . '.zip';
        $zip = new ZipArchive();
        if ($zip->open($zipFilePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException("无法创建ZIP文件: {$zipFilePath}");
        }

        $this->addDirToZip($zip, $pluginSourceDir, '');
        $zip->close();

        // 3. 检查是否有前端资源
        $hasFrontend = false;
        $projectPath = dirname(base_path());
        $adminAddonDir = $projectPath . '/template/mono/apps/admin/src/plugin/' . $pluginKey;
        $webAddonDir = $projectPath . '/template/web/app/plugin/' . $pluginKey;
        if (is_dir($adminAddonDir) || is_dir($webAddonDir)) {
            $hasFrontend = true;
        }

        return [
            'plugin_key' => $pluginKey,
            'zip_path' => $zipFilePath,
            'has_frontend' => $hasFrontend,
        ];
    }

    /**
     * 递归添加目录到ZIP
     */
    private function addDirToZip(ZipArchive $zip, string $dir, string $relativePath): void
    {
        $files = scandir($dir);
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }
            $fullPath = $dir . DIRECTORY_SEPARATOR . $file;
            $zipPath = $relativePath ? $relativePath . '/' . $file : $file;
            if (is_dir($fullPath)) {
                $zip->addEmptyDir($zipPath);
                $this->addDirToZip($zip, $fullPath, $zipPath);
            } else {
                $zip->addFile($fullPath, $zipPath);
            }
        }
    }

    /**
     * 扫描插件目录，同步到数据库
     */
    public function syncPlugins(): void
    {
        if (!is_dir($this->pluginDir)) {
            return;
        }

        $dirs = scandir($this->pluginDir);
        foreach ($dirs as $dir) {
            if ($dir === '.' || $dir === '..') {
                continue;
            }

            $pluginPath = $this->pluginDir . DIRECTORY_SEPARATOR . $dir;
            if (!is_dir($pluginPath)) {
                continue;
            }

            $infoFile = $pluginPath . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'info.php';
            // 兼容旧版：如果 config/info.php 不存在，尝试根目录 info.php
            if (!is_file($infoFile)) {
                $infoFile = $pluginPath . DIRECTORY_SEPARATOR . 'info.php';
            }
            if (!is_file($infoFile)) {
                continue;
            }

            $info = require $infoFile;
            if (empty($info['key']) || $info['key'] !== $dir) {
                continue;
            }

            // 读取 installed.php 判断真实安装状态（平台级安装标记）
            $installedPath = $pluginPath . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'installed.php';
            $installedAt   = null;
            if (is_file($installedPath)) {
                $installedConfig = include $installedPath;
                if (is_array($installedConfig) && !empty($installedConfig['installed_at'])) {
                    $raw = $installedConfig['installed_at'];
                    $installedAt = is_numeric($raw) ? (int) $raw : strtotime($raw);
                    if ($installedAt === false) {
                        $installedAt = null;
                    }
                }
            }

            $existing = $this->dao->findByKey($dir);
            $data = [
                'key'          => $info['key'] ?? $dir,
                'title'        => $info['title'] ?? $dir,
                'desc'         => $info['desc'] ?? '',
                'author'       => $info['author'] ?? '',
                'version'      => $info['version'] ?? '1.0.0',
                'type'         => $info['type'] ?? 'custom',
                'status'       => $info['status'] ?? 1,
                'icon'         => $info['icon'] ?? '',
                'cover'        => $info['cover'] ?? '',
                'support_app'  => $info['support_app'] ?? 'admin',
                'installed_at' => $installedAt,
            ];

            if ($existing) {
                $this->dao->update($existing->id, $data);
            } else {
                $data['id'] = Snowflake::generate();
                $this->dao->save($data);
            }
        }
    }

    /**
     * 创建插件目录结构和文件（匹配 backend/plugin/demo）
     */
    private function createPluginStructure(string $key, array $data): void
    {
        $ds = DIRECTORY_SEPARATOR;
        $pluginDir = $this->pluginDir . $ds . $key;

        // 创建目录结构（匹配 demo 标准）
        $dirs = [
            $pluginDir,
            $pluginDir . $ds . 'config',
            $pluginDir . $ds . 'app',
            $pluginDir . $ds . 'app' . $ds . 'model',
            $pluginDir . $ds . 'app' . $ds . 'dao',
            $pluginDir . $ds . 'app' . $ds . 'adminapi' . $ds . 'controller',
            $pluginDir . $ds . 'app' . $ds . 'adminapi' . $ds . 'validate',
            $pluginDir . $ds . 'app' . $ds . 'adminapi' . $ds . 'schema',
            $pluginDir . $ds . 'app' . $ds . 'api' . $ds . 'controller',
            $pluginDir . $ds . 'app' . $ds . 'api' . $ds . 'validate',
            $pluginDir . $ds . 'app' . $ds . 'api' . $ds . 'schema',
            $pluginDir . $ds . 'app' . $ds . 'service' . $ds . 'admin',
            $pluginDir . $ds . 'app' . $ds . 'service' . $ds . 'api',
            $pluginDir . $ds . 'public',
            $pluginDir . $ds . 'resource' . $ds . 'database' . $ds . 'migrations',
            $pluginDir . $ds . 'resource' . $ds . 'database' . $ds . 'seeds',
            $pluginDir . $ds . 'resource' . $ds . 'data' . $ds . 'menu',
            $pluginDir . $ds . 'resource' . $ds . 'data' . $ds . 'config',
            $pluginDir . $ds . 'resource' . $ds . 'template' . $ds . 'admin',
            $pluginDir . $ds . 'resource' . $ds . 'template' . $ds . 'web',
        ];

        foreach ($dirs as $dir) {
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
        }

        // 生成 config/app.php
        file_put_contents($pluginDir . $ds . 'config' . $ds . 'app.php', $this->buildAppConfig($key, $data));
        // 生成 config/info.php（合并了 writePluginInfo 和 buildInfoConfig 的字段，可被 syncPlugins 扫描）
        $this->writePluginInfo($pluginDir, $key, $data);
        // 生成 config/route.php
        file_put_contents($pluginDir . $ds . 'config' . $ds . 'route.php', $this->buildRouteConfig($key, $data));
        // 生成 config/translation.php
        file_put_contents($pluginDir . $ds . 'config' . $ds . 'translation.php', $this->buildTranslationConfig());
        // 生成 config/review.php
        file_put_contents($pluginDir . $ds . 'config' . $ds . 'review.php', $this->buildReviewConfig());
        // 生成 .gitignore
        file_put_contents($pluginDir . $ds . '.gitignore', "/config/installed.php\n");
        // 生成 Install.php（根目录）
        file_put_contents($pluginDir . $ds . 'Install.php', $this->buildInstallFile($key));
        // 生成控制器
        file_put_contents($pluginDir . $ds . 'app' . $ds . 'adminapi' . $ds . 'controller' . $ds . $this->toCamelCase($key) . 'Controller.php', $this->buildControllerFile($key, $data));
        // 生成菜单
        file_put_contents($pluginDir . $ds . 'resource' . $ds . 'data' . $ds . 'menu' . $ds . 'admin.php', $this->buildMenuAdmin($key, $data));
        file_put_contents($pluginDir . $ds . 'resource' . $ds . 'data' . $ds . 'menu' . $ds . 'web.php', "<?php\n\n/**\n * Web 菜单配置\n */\n\nreturn [\n    // Web 菜单配置\n];\n");
        // 生成前端模板到 resource/template/ 目录
        $this->generateFrontendTemplates($pluginDir, $ds, $key, $data);
        // 创建 .gitkeep 文件占位
        $this->createGitkeepFiles($pluginDir, $ds);
        // 初始化 public 资源（支持上传的图标/封页 base64 转换）
        $this->initPublicAssets($pluginDir, $ds, $data);
    }

    /**
     * 生成前端模板文件到 resource/template/ 目录
     * 安装时由 Install.php 的 importTemplates() 复制到前端项目
     */
    private function generateFrontendTemplates(string $pluginDir, string $ds, string $key, array $data): void
    {
        $camel    = $this->toCamelCase($key);
        $title    = $data['title'] ?? $key;
        $template = $pluginDir . $ds . 'resource' . $ds . 'template';

        // ---- Admin 模板 ----
        $adminDir = $template . $ds . 'admin';

        // 创建按 test 示例模块归类的子目录
        $pluginSubDirs = [
            'api' . $ds . 'test',
            'mock', 'routes',
            'views' . $ds . 'test', 'views' . $ds . 'test' . $ds . 'schemas',
        'lang' . $ds . 'zh-CN',
        'lang' . $ds . 'en-US',
        ];
        foreach ($pluginSubDirs as $sub) {
            $d = $adminDir . $ds . $sub;
            if (!is_dir($d)) {
                mkdir($d, 0755, true);
            }
        }

        // api/test/index.ts — BaseService 模式
        file_put_contents($adminDir . $ds . 'api' . $ds . 'test' . $ds . 'index.ts', <<<TS
import BaseService from '#/api/core/base';
import { requestClient } from '#/api/request';
import type { {$camel}Item } from './types';

const baseUrl = '/{$key}';

export const {$camel}Service = {
  ...BaseService<{$camel}Item>({ baseUrl }),

  /** 获取列表 */
  getList(params?: Record<string, any>) {
    return requestClient.get(`\${baseUrl}/index`, { params });
  },

  /** 获取详情 */
  getDetail(id: number | string) {
    return requestClient.get(`\${baseUrl}/view/\${id}`);
  },

  /** 新增 */
  createItem(data: Record<string, any>) {
    return requestClient.post(`\${baseUrl}/create`, data);
  },

  /** 更新 */
  updateItem(id: number | string, data: Record<string, any>) {
    return requestClient.put(`\${baseUrl}/update/\${id}`, data);
  },

  /** 删除（批量） */
  deleteItem(ids: number[] | string[]) {
    return requestClient.delete(`\${baseUrl}/delete`, { data: { ids } });
  },
};
TS
);
        // api/test/types.ts
        file_put_contents($adminDir . $ds . 'api' . $ds . 'test' . $ds . 'types.ts', <<<TS
/**
 * {$title} 插件 API 类型定义
 */

/** {$title} 记录 */
export interface {$camel}Item {
  /** 主键 ID */
  id: number | string;
  /** 创建时间 */
  created_at?: string;
  /** 更新时间 */
  updated_at?: string;
  [key: string]: any;
}
TS
);
        // mock/api.ts（平铺，共享）
        file_put_contents($adminDir . $ds . 'mock' . $ds . 'api.ts', <<<TS
import { MOCK_TABLE_DATA } from './table-data';

export function getTableListApi(params: Record<string, any>) {
  const { page = 1, pageSize = 20 } = params;
  const items = MOCK_TABLE_DATA.slice((page - 1) * pageSize, page * pageSize);
  return Promise.resolve({ items, total: MOCK_TABLE_DATA.length });
}
TS
);
        // mock/table-data.ts（平铺，共享）
        file_put_contents($adminDir . $ds . 'mock' . $ds . 'table-data.ts', <<<TS
export const MOCK_TABLE_DATA = Array.from({ length: 50 }, (_, i) => ({
  id: i + 1,
  name: `Item \${i + 1}`,
  status: i % 3 === 0 ? 'enabled' : 'disabled',
  createdAt: new Date(Date.now() - i * 86400000).toISOString().slice(0, 10),
}));
TS
);
        // routes/index.ts（平铺，共享，meta 加 module）
        file_put_contents($adminDir . $ds . 'routes' . $ds . 'index.ts', <<<TS
import { \$t } from '#/locales';

export default [
  {
    path: '/{$key}/index',
    name: '{$camel}Index',
    component: 'test/index',
    meta: {
      title: \$t('{$key}.test.title'),
      icon: 'lucide:plugin',
      order: 1,
      module: '{$key}',
    },
  },
];
TS
);
        // views/test/schemas/index.tsx — CRUD Schema（Service 模式）
        file_put_contents($adminDir . $ds . 'views' . $ds . 'test' . $ds . 'schemas' . $ds . 'index.tsx', <<<TS
import { \$t } from '#/locales';
import type { CrudSchema } from '#/components/crud/components/types';
import { {$camel}Service } from '#/plugin/{$key}/api/test';

export function useCrudSchema(): CrudSchema {
  return {
    crudApi: {
      list: {$camel}Service.getList,
      add: {$camel}Service.createItem,
      edit: {$camel}Service.updateItem,
      remove: {$camel}Service.deleteItem,
      batchRemove: {$camel}Service.deleteItem,
      view: {$camel}Service.getDetail,
    },
    hasAdd: true,
    hasEdit: true,
    hasView: true,
    hasRemove: true,
    hasBatchRemove: true,
    permissions: {
      add: '{$key}:create',
      edit: '{$key}:update',
      remove: '{$key}:delete',
      view: '{$key}:read',
    },
    columns: [
      { type: 'checkbox', width: 60 },
      { field: 'id', title: 'ID', width: 80, visible: false },
      { field: 'name', title: \$t('{$key}.test.name'), minWidth: 150 },
      { field: 'status', title: \$t('{$key}.test.status'), width: 100,
        cellRender: { name: 'CellDictTag', attrs: { code: 'sys_enabled_status' } } },
      { field: 'sort', title: \$t('{$key}.test.sort'), width: 80 },
      { field: 'remark', title: \$t('{$key}.test.remark'), minWidth: 200,
        showOverflowTooltip: true },
      { field: 'created_at', title: \$t('{$key}.test.created_at'), width: 180 },
    ],
    searchForm: {
      enabled: true,
      collapsed: true,
      collapsedRows: 2,
      submitOnChange: true,
      schema: [
        { component: 'Input', fieldName: 'LIKE_name', label: \$t('{$key}.test.name'),
          componentProps: { clearable: true, placeholder: ' ' } },
        { component: 'ApiDict', fieldName: 'EQ_status', label: \$t('{$key}.test.status'),
          componentProps: { code: 'sys_enabled_status', clearable: true } },
      ],
    },
    formDialog: {
      enabled: true,
      title: '{$title}',
      width: 'w-[50%]',
      dialogType: 'drawer',
      wrapperClass: 'grid-cols-2',
      commonConfig: { labelWidth: 100, labelAlign: 'right' },
      schema: [
        { fieldName: 'id', label: 'ID', component: 'Input',
          dependencies: { triggerFields: ['id'], show: false } },
        { fieldName: 'name', label: \$t('{$key}.test.name'), component: 'Input',
          rules: 'required', formItemClass: 'col-span-1' },
        { fieldName: 'status', label: \$t('{$key}.test.status'), component: 'ApiDict',
          defaultValue: 1,
          componentProps: { code: 'sys_enabled_status', renderType: 'RadioGroup', isBtn: true } },
        { fieldName: 'sort', label: \$t('{$key}.test.sort'), component: 'InputNumber',
          defaultValue: 0, componentProps: { min: 0, max: 99999 } },
        { fieldName: 'remark', label: \$t('{$key}.test.remark'), component: 'Textarea',
          formItemClass: 'col-span-2' },
      ],
    },
  };
}
TS
);
        // views/test/index.vue — 标准 CRUD 视图
        file_put_contents($adminDir . $ds . 'views' . $ds . 'test' . $ds . 'index.vue', <<<VUE
<script setup lang="ts">
import { useCrud } from '#/adapter/crud';
import { Page } from '#/components/page';
import { useCrudSchema } from './schemas';

const [BasicCrud] = useCrud(useCrudSchema());
</script>

<template>
  <Page auto-content-height>
    <BasicCrud />
  </Page>
</template>
VUE
);
        // lang/zh-CN/test.json
        file_put_contents($adminDir . $ds . 'lang' . $ds . 'zh-CN' . $ds . 'test.json',
            json_encode([
                'title'      => $title,
                'name'       => '名称',
                'status'     => '状态',
                'sort'       => '排序',
                'remark'     => '备注',
                'created_at' => '创建时间',
            ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n");
        // lang/en-US/test.json
        file_put_contents($adminDir . $ds . 'lang' . $ds . 'en-US' . $ds . 'test.json',
            json_encode([
                'title'      => $title,
                'name'       => 'Name',
                'status'     => 'Status',
                'sort'       => 'Sort',
                'remark'     => 'Remark',
                'created_at' => 'Created At',
            ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n");

        // ---- Web 模板 ----
        $webDir = $template . $ds . 'web';
        $pagesDir = $webDir . $ds . 'pages';
        if (!is_dir($pagesDir)) {
            mkdir($pagesDir, 0755, true);
        }
        // pages/routes.ts
        file_put_contents($webDir . $ds . 'pages' . $ds . 'routes.ts', "export default [\n\n]\n");
    }

    /**
     * 为空目录创建 .gitkeep
     */
    private function createGitkeepFiles(string $pluginDir, string $ds): void
    {
        $paths = [
            'app' . $ds . 'model',
            'app' . $ds . 'dao',
            'app' . $ds . 'adminapi' . $ds . 'validate',
            'app' . $ds . 'adminapi' . $ds . 'schema',
            'app' . $ds . 'api' . $ds . 'controller',
            'app' . $ds . 'api' . $ds . 'validate',
            'app' . $ds . 'api' . $ds . 'schema',
            'app' . $ds . 'service' . $ds . 'admin',
            'app' . $ds . 'service' . $ds . 'api',
            'resource' . $ds . 'database' . $ds . 'migrations',
            'resource' . $ds . 'database' . $ds . 'seeds',
            'resource' . $ds . 'data' . $ds . 'config',
        ];
        foreach ($paths as $path) {
            $dir = $pluginDir . $ds . $path;
            if (is_dir($dir)) {
                file_put_contents($dir . $ds . '.gitkeep', '');
            }
        }
    }

    /**
     * 初始化 public 资源（支持上传的图标/封页 base64 转换）
     */
    private function initPublicAssets(string $pluginDir, string $ds, array $data = []): void
    {
        $publicDir = $pluginDir . $ds . 'public';
        if (!is_dir($publicDir)) {
            mkdir($publicDir, 0755, true);
        }
        // icon.png - 尝试 base64 转换，失败则自动兜底占位图
        $iconPath = $publicDir . $ds . 'icon.png';
        if (!empty($data['icon'])) {
            $this->saveBase64Image($data['icon'], $iconPath);
        }
        if (!file_exists($iconPath)) {
            $this->savePlaceholderPng($iconPath, true);
        }
        // cover.png - 尝试 base64 转换，失败则自动兜底占位图
        $coverPath = $publicDir . $ds . 'cover.png';
        if (!empty($data['cover'])) {
            $this->saveBase64Image($data['cover'], $coverPath);
        }
        if (!file_exists($coverPath)) {
            $this->savePlaceholderPng($coverPath, false);
        }
    }

    /**
     * 保存 base64 图片
     */
    private function saveBase64Image(string $data, string $path): void
    {
        // 去掉 data:image/...;base64, 前缀
        if (str_contains($data, 'base64,')) {
            $data = substr($data, strpos($data, 'base64,') + 7);
        }
        $decoded = base64_decode($data, true);
        if ($decoded !== false) {
            file_put_contents($path, $decoded);
        }
    }

    /**
     * 保存占位 PNG（可见图像，非透明）
     */
    private function savePlaceholderPng(string $path, bool $forIcon): void
    {
        if ($forIcon) {
            // 60x60 蓝色方块带白色圆点（可见图标）
            $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAADwAAAA8CAIAAAC1nk4lAAAACXBIWXMAAA7EAAAOxAGVKw4bAAAA7UlEQVRoge3avQ3CMBiEYQchMRcbMAw9omcYNmAlCgroKNxAEgfiO11iffd25Ac9+oQSW6K7P56ptTZLA2oyWpXRqoxWtS2dOFx2Skep6/E1PNjkpI1WZbQqo1UZrcpoVcW1B9Lt9PVxfyZ/Pxnd414eJNJp6FHu8AIKnfOb/imuuHIiAnquA3c3+fRA0XVjA4cdb9LIwJB74016qYxWBaGRdzJyb7xJp9qBgcumkJNO88eGr045k/7fQVlP0zYBWTPxcl7jziU3Sl/7HjFHV/aK+vTQZ7Qqo1UZrcpoVU2iO//xSpTRqoxW1ST6DTNZJ/95fTe6AAAAAElFTkSuQmCC');
        } else {
            // 300x160 蓝色头部 + 灰色色块（可见封面）
            $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAASwAAACgCAIAAAA5GsY1AAAACXBIWXMAAA7EAAAOxAGVKw4bAAACBUlEQVR4nO3VoRHCQBRFUcJQBgpFGZRGmSgUBURExAWHAAnMFXuO351n7vzpct12QGdfD4DRiRBiIoSYCCEmQoiJEGIihJgIISZCiIkQYiKEmAghJkKIiRBiIoSYCCEmQoiJEGIihJgIISZCiIkQYiKEmAghJkKIiRBiIoSYCCEmQoiJEGIihJgIISZCiIkQYiKEmAghJkKIiRBiIoSYCCEmQoiJEGIihJgIISZCiIkQYiKEmAghJkKIiRBiIoSYCCEmQoiJEGIihJgIISZCiIkQYiKEmAghJkKIiRBiIoSYCCEmQoiJEGIihJgIITbNy1pvgKG5hBATIcRECDERQkyEEBMhxEQIMRFCTIQQEyHERAgxEUJMhBATIcRECDERQkyEEBMhxEQIMRFCTIQQEyHERAgxEUJMhBATIcRECDERQkyEEBMhxEQIMRFCTIQQEyHERAgxEUJMhBATIcRECDERQkyEEBNh86z1Bhiay3/gwOHD7QkAAAAASUVORK5CYII=');
        }
        file_put_contents($path, $png);
    }

    /**
     * 转驼峰命名
     */
    private function toCamelCase(string $string): string
    {
        return str_replace(' ', '', ucwords(str_replace('_', ' ', $string)));
    }

    /**
     * 构建 app.php
     */
    private function buildAppConfig(string $key, array $data): string
    {
        $stub = file_get_contents(__DIR__ . '/../../core/plugin/stubs/admin/app/config.stub');
        return str_replace(
            ['{{pluginVersion}}', '{{pluginTitle}}', '{{pluginDesc}}', '{{pluginAuthor}}'],
            [$data['version'], $data['title'], $data['desc'], $data['author']],
            $stub
        );
    }

    /**
     * 构建 route.php
     */
    private function buildRouteConfig(string $key, array $data): string
    {
        $stub = file_get_contents(__DIR__ . '/../../core/plugin/stubs/admin/route/route.stub');
        return str_replace(
            ['{{pluginKey}}', '{{kebabPluginKey}}', '{{pluginTitle}}', '{{pluginVersion}}'],
            [$key, str_replace('_', '-', $key), $data['title'], $data['version']],
            $stub
        );
    }

    /**
     * 构建 translation.php
     */
    private function buildTranslationConfig(): string
    {
        return "<?php\n\n/**\n * Multilingual configuration\n */\n\nuse app\\enum\\common\\LangEnum;\n\nreturn [\n    'locale'          => config('app.lang'),\n    'fallback_locale' => ['zh_CN', 'en'],\n    'path'            => base_path() . '/resource/translations',\n];\n";
    }

    /**
     * 构建 review.php
     */
    private function buildReviewConfig(): string
    {
        return "<?php\n\nreturn [\n    /*\n     * 审核配置\n     * 用于定义数据审核流程中的字段映射规则\n     */\n];\n";
    }

    /**
     * 构建 Install.php
     */
    private function buildInstallFile(string $key): string
    {
        $stub = file_get_contents(__DIR__ . '/../../core/plugin/stubs/admin/install/Install.stub');
        return str_replace(
            ['{{pluginKey}}', '{{camelPluginKey}}'],
            [$key, $this->toCamelCase($key)],
            $stub
        );
    }

    /**
     * 构建控制器文件
     */
    private function buildControllerFile(string $key, array $data): string
    {
        $stub = file_get_contents(__DIR__ . '/../../core/plugin/stubs/admin/controller/Controller.stub');
        return str_replace(
            ['{{pluginKey}}', '{{camelPluginKey}}', '{{kebabPluginKey}}'],
            [$key, $this->toCamelCase($key), str_replace('_', '-', $key)],
            $stub
        );
    }

    /**
     * 构建 admin 菜单配置
     */
    private function buildMenuAdmin(string $key, array $data): string
    {
        $stub = file_get_contents(__DIR__ . '/../../core/plugin/stubs/admin/menu/menu.stub');
        return str_replace(
            ['{{pluginTitle}}', '{{kebabPluginKey}}'],
            [$data['title'] ?? $key, str_replace('_', '-', $key)],
            $stub
        );
    }

    /**
     * 写入插件配置信息到 config/info.php
     * 合并了 Demo 标准字段 + syncPlugins 扫描所需字段
     */
    private function writePluginInfo(string $pluginDir, string $key, array $data): void
    {
        $configDir = $pluginDir . DIRECTORY_SEPARATOR . 'config';
        if (!is_dir($configDir)) {
            mkdir($configDir, 0755, true);
        }
        $infoFile = $configDir . DIRECTORY_SEPARATOR . 'info.php';
        $info = [
            // Demo 标准字段（config/info.php）
            'name'         => $key,
            'identifier'   => $key,
            // syncPlugins 扫描字段
            'key'          => $key,
            'title'        => $data['title'] ?? $key,
            'description'  => $data['desc'] ?? '',
            'desc'         => $data['desc'] ?? '',
            'author'       => $data['author'] ?? '未知',
            'author_email' => '',
            'version'      => $data['version'] ?? '1.0.0',
            'type'         => $data['type'] ?? 'madong:app',
            'website'      => 'https://madong.tech',
            'icon'         => $data['icon'] ?? '',
            'cover'        => $data['cover'] ?? '',
            'support_app'  => $data['support_app'] ?? '',
            'status'       => 1,
            'uninstall'    => [
                'drop_tables'         => false,
                'remove_dependencies' => false,
            ],
        ];
        file_put_contents($infoFile, '<?php return ' . var_export($info, true) . ';');
    }
}
