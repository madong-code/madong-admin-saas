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
/**
 * 插件安装基类
 * 使用 Trait 实现职责分离：
 * - ConfigTrait: 配置管理
 * - MigrationTrait: 迁移操作
 * - SeedTrait: 种子操作
 * - MenuTrait: 菜单操作
 * - TemplateTrait: 模板资源操作
 * - DependencyTrait: 依赖安装
 * 使用方式：
 * 1. 在插件目录创建 Install.php
 * 2. 继承此类即可，迁移和种子会自动执行
 * 3. 可重写 install/uninstall/update 方法添加自定义逻辑
 * 配置覆盖：
 * - 默认配置: core/plugin/config/default.php
 * - 应用配置: core/plugin/config/app.php
 * - 插件配置: plugin/{name}/config/info.php (可覆盖上述配置)
 */

namespace core\business\plugin;

use core\business\plugin\traits\ConfigTrait;
use core\business\plugin\traits\MigrationTrait;
use core\business\plugin\traits\SeedTrait;
use core\business\plugin\traits\MenuTrait;
use core\business\plugin\traits\TemplateTrait;
use core\business\plugin\traits\DependencyTrait;
use core\business\message\MessageDataSync;
use core\business\tenant\context\TenantContext;

class PluginInstall
{
    use ConfigTrait;
    use MigrationTrait;
    use SeedTrait;
    use MenuTrait;
    use TemplateTrait;
    use DependencyTrait;

    /** @var string 插件名称 */
    protected string $pluginName = '';

    /** @var string 插件路径 */
    protected string $pluginPath = '';

    /** @var string|null 连接名（默认使用框架配置） */
    protected ?string $connection = null;

    /** @var array 插件配置 */
    protected array $appConfig = [];

    /** @var callable|null 进度回调函数（用于在线模式通过 SSE 返回进度） */
    protected $progressCallback = null;

    /** @var bool 是否为在线模式（非 CLI） */
    protected bool $isOnlineMode = false;

    /** @var string 安装上下文 'platform' | 'tenant' */
    protected string $context = 'platform';

    /** @var string|int|null 租户ID（tenant上下文时使用） */
    protected string|int|null $tenantId = null;

    /** @var string|null 执行日志文件路径 */
    protected ?string $executionLogFile = null;

    /** @var int 执行开始时间戳（微秒） */
    protected float $executionStartTime = 0;

    /**
     * @param callable|null $progressCallback 进度回调
     * @param bool          $isOnlineMode     是否为在线模式
     */
    public function __construct(?callable $progressCallback = null, bool $isOnlineMode = false)
    {
        $this->progressCallback = $progressCallback;
        $this->isOnlineMode     = $isOnlineMode;
        $this->init();
    }

    /**
     * 设置进度回调
     */
    public function setProgressCallback(?callable $callback): self
    {
        $this->progressCallback = $callback;
        return $this;
    }

    /**
     * 设置是否为在线模式
     */
    public function setOnlineMode(bool $isOnlineMode): self
    {
        $this->isOnlineMode = $isOnlineMode;
        return $this;
    }

    /**
     * 获取执行日志文件路径
     */
    protected function getExecutionLogFile(string $action): string
    {
        $dir = runtime_path('migrations/plugin/' . $this->pluginName);
        $dir = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $dir);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        return $dir . DIRECTORY_SEPARATOR . $action . '-' . date('Ymd-His') . '.log';
    }

    /**
     * 写入执行日志行
     */
    protected function writeExecutionLog(string $message): void
    {
        if (!$this->executionLogFile || !file_exists($this->executionLogFile)) {
            return;
        }
        $time = date('Y-m-d H:i:s');
        $line = "[{$time}] {$message}" . PHP_EOL;
        @file_put_contents($this->executionLogFile, $line, FILE_APPEND | LOCK_EX);
    }

    /**
     * 初始化执行日志（写入文件头）
     * 如果日志目录创建或文件写入失败，自动降级为无日志模式，不阻塞业务流程
     */
    protected function initExecutionLog(string $action, string $version): void
    {
        $this->executionStartTime = microtime(true);

        try {
            $this->executionLogFile = $this->getExecutionLogFile($action);

            $header = "# ============================================================" . PHP_EOL;
            $header .= "# Plugin Execution Log" . PHP_EOL;
            $header .= "# Plugin: {$this->pluginName} / Operation: {$action} / Version: {$version}" . PHP_EOL;
            $header .= "# Context: {$this->context} / Started: " . date('Y-m-d H:i:s') . PHP_EOL;
            $header .= "# ============================================================" . PHP_EOL;

            $logDir = dirname($this->executionLogFile);
            $logDir = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $logDir);
            if (!is_dir($logDir)) {
                @mkdir($logDir, 0755, true);
            }
            if (is_dir($logDir)) {
                file_put_contents($this->executionLogFile, $header, LOCK_EX);
            } else {
                $this->executionLogFile = null;
            }
        } catch (\Throwable $e) {
            // 日志文件创建失败，降级为无日志模式
            $this->executionLogFile = null;
        }
    }

    /**
     * 完成执行日志（写入状态和耗时摘要）
     */
    protected function finishExecutionLog(string $status = 'success'): void
    {
        if (!$this->executionLogFile) {
            return;
        }
        $duration = round((microtime(true) - $this->executionStartTime) * 1000);
        $this->writeExecutionLog("# Status: {$status} / Duration: {$duration}ms");
        $this->executionLogFile = null;
    }

    /**
     * 输出日志（CLI 模式 echo，在线模式通过回调，同时写入执行日志文件）
     */
    protected function log(string $message, ?int $progress = null): void
    {
        if ($this->executionLogFile) {
            $this->writeExecutionLog($message);
        }
        if ($this->isOnlineMode && $this->progressCallback) {
            ($this->progressCallback)($message, $progress);
        } else {
            echo $message . "\n";
        }
    }

    /**
     * 输出进度
     */
    protected function progress(string $message, int $progress): void
    {
        $this->log($message, $progress);
    }

    /**
     * 输出消息（用于 Trait 中的 echo 替换）
     * 统一走 log() 方法：写入执行日志 + 回调输出 + CLI echo
     */
    protected function output(string $message): void
    {
        $this->log($message);
    }

    /**
     * 初始化
     */
    protected function init(): void
    {
        $this->pluginPath = $this->getPluginPath();
        $this->pluginName = basename($this->pluginPath);
        $this->appConfig  = $this->getAppConfig();
    }

    /**
     * 插件路径 - 子类可重写
     */
    protected function getPluginPath(): string
    {
        $class = static::class;
        $parts = explode('\\', $class);
        if (count($parts) >= 2 && $parts[0] === 'plugin') {
            return base_path('plugin/' . $parts[1]);
        }
        return base_path('plugin');
    }

    /**
     * 插件名称
     */
    protected function getPluginName(): string
    {
        return $this->pluginName ?: basename($this->getPluginPath());
    }

    /**
     * 设置数据库连接
     */
    public function setConnection(?string $connection): self
    {
        $this->connection = $connection;
        return $this;
    }

    /**
     * 设置安装上下文
     *
     * @param string $context 'platform' | 'tenant'
     * @return self
     */
    public function setContext(string $context): self
    {
        $this->context = $context;
        return $this;
    }

    /**
     * 设置租户ID
     *
     * @param string|int|null $tenantId
     * @return self
     */
    public function setTenantId(string|int|null $tenantId): self
    {
        $this->tenantId = $tenantId;
        return $this;
    }

    /**
     * 是否为平台上下文
     */
    protected function isPlatformContext(): bool
    {
        return $this->context === 'platform';
    }

    /**
     * 是否为DB模式租户安装
     */
    protected function isTenantDbMode(): bool
    {
        return $this->context === 'tenant'
            && TenantContext::getIsolationMode() === 'database';
    }

    /**
     * 强制重新运行迁移
     */
    public function forceMigrate(): void
    {
        $this->output("⚠️ Force migration - clearing migration logs...");
        $this->clearLogs();
        $this->runMigrations();
    }

    /**
     * 安装（支持平台/租户上下文）
     *
     * 需求矩阵:
     *  - 单体模式        : 代码部署 + 迁移建表(主库) + 菜单(sys_menu) + installed.php
     *  - 租户(字段隔离)  : 代码(平台已部署) + 数据表(平台建共享主库表) ; 菜单由租户安装模块安装
     *  - 租户(库隔离)    : 代码(平台已部署) ; 数据表(租户库) + 菜单 均"租户安装模块"安装
     * 即: PluginInstall 阶段只负责"单体"的菜单与建表; 多租户下菜单/租户库表由 TenantMenuSyncService / TenantPluginMigrationService 负责。
     */
    public function install($version): void
    {
        $this->initExecutionLog('install', $version);

        $this->log("[START] Plugin Install: {$this->getPluginName()}", 0);
        $this->log("Version: {$version}, Context: {$this->context}");

        // 安装前回调
        $this->beforeInstall($version);

        // 代码部署（Composer/NPM/模板）：仅平台/无租户
        if ($this->isPlatformContext()) {
            $this->installComposerDeps();
            $this->installNpmDeps();
            $this->copyTemplates();
            $this->runInstallCommands();
        }

        // 迁移建表策略(匹配需求矩阵):
        //  - 单体(single)        : 主库建表
        //  - 平台(field 隔离)     : 主库建表(字段隔离共享主库表)
        //  - 平台(database 隔离) : 仅代码, 不建表(表由"租户安装模块"在租户库创建)
        //  - 租户(database 隔离) : 租户库建表(上下文 + 连接已设为租户)
        //  - 租户(field 隔离)    : 跳过(共享主库表由平台建)
        $isDbPlatform = $this->isPlatformContext()
            && !TenantContext::isSingleMode()
            && TenantContext::getIsolationMode() === 'database';
        if (!$isDbPlatform) {
            $this->runMigrations();
            $this->runSeeds();
        }

        // 菜单安装：仅单体模式在 afterInstall→loadMenus 阶段落 sys_menu;
        // 多租户菜单由「租户安装模块」(TenantMenuSyncService) 在租户安装/开通时同步, 此处不做
        $this->afterInstall($version);

        // installed.php：仅平台/无租户
        if ($this->isPlatformContext()) {
            $this->savePluginConfig($version);
        }

        $this->log("[DONE] Install Completed", 100);
        $this->finishExecutionLog('success');
    }

    /**
     * 安装前回调 - 可重写
     */
    protected function beforeInstall(string $version): void
    {
        $this->log("  🔄 Running beforeInstall...");
    }

    /**
     * 安装后回调 - 可重写
     */
    protected function afterInstall(string $version): void
    {
        $this->log("  🔄 Running afterInstall...");

        // 加载插件菜单
        $this->loadMenus();

        // 导入消息分类/模块数据
        $this->importMessageData();
    }

    /**
     * 卸载前回调 - 可重写
     */
    protected function beforeUninstall(string $version): void
    {
        $this->log("  🔄 Running beforeUninstall...");
    }

    /**
     * 卸载后回调 - 可重写
     */
    protected function afterUninstall(string $version): void
    {
        $this->log("  🔄 Running afterUninstall...");

        // 删除插件菜单
        $this->clearMenus();

        // 清理插件消息分类/定义/模板（按 source 精确清理）
        $this->clearMessageData();
    }

    /**
     * 卸载（支持平台/租户上下文）
     *
     * 平台上下文：回滚迁移 + 菜单清理 + 代码清理 + installed.php删除
     * 租户FIELD：仅菜单清理
     * 租户DB：回滚迁移(租户库) + 菜单清理
     */
    public function uninstall($version): void
    {
        $this->initExecutionLog('uninstall', $version);

        $this->log("[START] Plugin Uninstall: {$this->getPluginName()}", 0);
        $this->log("Context: {$this->context}");

        // 卸载前回调
        $this->beforeUninstall($version);

        // 获取卸载配置
        $pluginInfo      = $this->getPluginInfo();
        $uninstallConfig = $pluginInfo['uninstall'] ?? [];
        $dropTables      = $uninstallConfig['drop_tables'] ?? false;

        // 回滚迁移：平台/无租户(主库) OR DB租户(租户库)
        if ($this->isPlatformContext() || $this->isTenantDbMode()) {
            $this->log("[RUN] Rollback migrations (drop_tables=" . ($dropTables ? 'true' : 'false') . ")");
            $this->rollbackMigrations($dropTables);
            $this->clearLogs();
        }

        // 菜单清理：所有场景都执行
        $this->afterUninstall($version);

        // 代码清理：仅平台/无租户
        if ($this->isPlatformContext()) {
            $this->deleteTemplates();

            $globalUninstallConfig = $this->getConfig('uninstall', []);
            $removeDependencies    = $uninstallConfig['remove_dependencies'] ?? $globalUninstallConfig['remove_dependencies'] ?? false;

            if ($removeDependencies) {
                $this->removeMergedDependencies();
            } else {
                $this->log("📦 Dependencies preserved in target projects. Remove manually if needed.");
            }

            $this->removePluginConfig();
        }

        // 卸载时清理日志目录
        $logDir = runtime_path('migrations/plugin/' . $this->pluginName);
        if (is_dir($logDir)) {
            $this->log("[RUN] Cleaning up logs directory");
            $this->removeDirectory($logDir);
            $this->log("[DONE] Logs directory removed");
        }

        $this->log("[DONE] Uninstall Completed", 100);
        $this->finishExecutionLog('success');
    }

    /**
     * 删除插件
     */
    public function delete($version): void
    {
        $this->log("====== Plugin Delete: {$this->getPluginName()} ======", 10);

        // 获取插件配置
        $pluginInfo      = $this->getPluginInfo();
        $uninstallConfig = $pluginInfo['uninstall'] ?? [];
        $undeletable     = $uninstallConfig['undeletable'] ?? false;

        $this->log("📋 Plugin config: undeletable=" . ($undeletable ? 'true' : 'false'), 20);

        // 检查是否允许删除
        if ($undeletable) {
            throw new \Exception('系统内置插件不允许删除');
        }

        // 删除前回调
        $this->beforeDelete($version);

        // 获取插件目录
        $pluginDir = $this->pluginPath;

        if (!is_dir($pluginDir)) {
            throw new \Exception('插件目录不存在：' . $pluginDir);
        }

        $this->log("📁 Deleting plugin directory: {$pluginDir}", 40);

        // 使用递归删除整个插件目录
        $this->removeDirectory($pluginDir);

        $this->log("✅ Plugin deleted successfully", 100);

        // 删除后回调
        $this->afterDelete($version);
    }

    /**
     * 删除前回调 - 可重写
     */
    protected function beforeDelete(string $version): void
    {
        $this->log("  🔄 Running beforeDelete...");
    }

    /**
     * 删除后回调 - 可重写
     */
    protected function afterDelete(string $version): void
    {
        $this->log("  🔄 Running afterDelete...");
    }

    /**
     * 递归删除目录
     */
    protected function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $files = scandir($directory);
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }

            $path = $directory . DIRECTORY_SEPARATOR . $file;

            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                @unlink($path);
            }
        }

        @rmdir($directory);
    }

    /**
     * 清除菜单 - 供子类在 afterUninstall 中调用
     */
    protected function clearMenus(): void
    {
        $this->clearPluginMenus($this->pluginName);
    }

    /**
     * 删除模板资源 - 供子类在 afterUninstall 中调用
     */
    protected function deletePluginTemplates(): void
    {
        $this->deleteTemplates();
    }

    /**
     * 导入消息分类/定义/模板数据
     *
     * 扫描插件 resource/data/message/category.php 文件并导入到数据库（source=plugin:{name}）。
     * 自动在 afterInstall 中调用，插件无需手动处理。
     *
     * 插件数据文件格式（与核心文件相同）：
     * [
     *   ['key' => 'cat_key', 'name' => '分类名', 'sort' => 10, 'definitions' => [
     *     ['key' => 'def_key', 'name' => '定义名', 'nav_type' => 'router', 'templates' => [
     *       ['type' => 'system', 'key' => 'tpl_key', 'title' => '标题', 'content_template' => '内容'],
     *     ]],
     *   ]],
     * ]
     *
     * 幂等：分类 source+key、定义 category_id+key、模板 type+key；
     * sync 模式会同时删除本来源中数据文件已移除的节点，重复安装不会产生脏数据。
     */
    protected function importMessageData(): void
    {
        $categories = $this->loadMessageFile();
        if ($categories === null) {
            return;
        }

        $stat = (new MessageDataSync('plugin:' . $this->getPluginName()))
            ->sync($categories, MessageDataSync::MODE_SYNC);

        $this->output(sprintf(
            '  ✅ Imported plugin message data (created %d, updated %d, deleted %d)',
            $stat['created'],
            $stat['updated'],
            $stat['deleted']
        ));
    }

    /**
     * 清理插件消息数据
     *
     * 按 source=plugin:{name} 精确清理分类/定义/模板及其关联，
     * 不影响 source=system（框架）与 source=user（后台自建）的数据。
     * 消息数据为平台级共享数据（tenant_id 为空），因此仅平台上下文执行清理。
     */
    protected function clearMessageData(): void
    {
        if (!$this->isPlatformContext()) {
            return;
        }

        $deleted = (new MessageDataSync('plugin:' . $this->getPluginName()))->purge();
        $this->output("  ✅ Cleared plugin message data (deleted {$deleted})");
    }

    /**
     * 读取插件消息数据文件
     */
    protected function loadMessageFile(): ?array
    {
        $catFile = $this->pluginPath . '/resource/data/message/category.php';
        if (!is_file($catFile)) {
            return null;
        }

        $categories = require $catFile;
        return is_array($categories) ? $categories : null;
    }

    /**
     * 更新
     */
    public function update($version): void
    {
        $this->initExecutionLog('update', $version);

        $this->log("[START] Plugin Update: {$this->getPluginName()}", 0);
        $this->log("Version: {$version}");

        // 先卸载（不清理日志目录，保留用于对比）
        $this->clearLogs();
        $this->rollbackMigrations(false);

        // 再安装
        $this->install($version);

        $this->log("[DONE] Update Completed", 100);
        $this->finishExecutionLog('success');
    }

    // =========================================================
    // 端安装命令执行
    // =========================================================

    /**
     * 执行各端安装命令
     */
    protected function runInstallCommands(): void
    {
        $this->output("📦 Running install commands...");

        $projectRoot = $this->getProjectRoot();

        // 后端安装命令
        $this->runBackendCommands($projectRoot);

        // 前端安装命令
        $this->runFrontendCommands($projectRoot);
    }

    /**
     * 执行后端安装命令
     */
    protected function runBackendCommands(string $projectRoot): void
    {
        // 可在子类中重写实现自定义命令
    }

    /**
     * 执行前端安装命令
     */
    protected function runFrontendCommands(string $projectRoot): void
    {
        // 可在子类中重写实现自定义命令
    }
}
