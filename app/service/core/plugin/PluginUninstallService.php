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

use app\event\plugin\PluginUninstalled;
use app\event\plugin\PluginUninstalling;
use app\service\core\plugin\PluginLifecycleOrchestrator;
use core\business\plugin\traits\ProgressStreamTrait;
use core\foundation\tool\Sse;
use core\io\uuid\UUIDGenerator;
use support\Container;

/**
 * 插件卸载服务（统一入口）
 * CLI 和在线卸载都统一调用此服务
 * CLI 模式通过迭代器获取 SSE 事件并解析
 * 在线模式直接返回 Generator 给前端处理
 */
class PluginUninstallService extends PluginBaseService
{
    use ProgressStreamTrait;
    /**
     * 卸载插件（统一入口）
     * 卸载流程统一使用插件自带的 Install.php
     * PluginUninstallService 只负责流程控制，实际卸载逻辑完全由插件 Install.php 处理
     *
     * @param string          $code     插件编码
     * @param string|int|null $tenantId 租户ID（null=平台卸载）
     *
     * @return \Generator CLI 模式返回数组，在线模式返回 Generator
     * @throws \Exception
     */
    public function uninstall(string $code, string|int|null $tenantId = null): \Generator
    {
        $sessionUuid = UUIDGenerator::generate();
        $request     = request();
        if ($request) {
            $sessionUuid = $request->input('uuid', $sessionUuid);
        }

        // 检查插件实体是否存在
        $pluginDir        = $this->plugin_path . DIRECTORY_SEPARATOR . $code;
        $pluginConfigFile = $pluginDir . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'info.php';

        if (!is_dir($pluginDir) || !is_file($pluginConfigFile)) {
            yield Sse::error('插件不存在，无法卸载', [], $sessionUuid);
            return;
        }

        // 检查插件是否允许卸载
        $pluginConfig = $this->getPluginConfig($code);
        if (isset($pluginConfig['uninstall']['undeletable']) && $pluginConfig['uninstall']['undeletable'] === true) {
            yield Sse::error('该插件为系统内置插件，不允许卸载', [], $sessionUuid);
            return;
        }

        // 检查插件是否提供 Install.php
        $className = "plugin\\{$code}\\Install";
        if (!class_exists($className)) {
            yield Sse::error("插件未提供 Install.php，无法进行卸载", [], $sessionUuid);
            return;
        }

        // 提前验证父类可加载，避免后续 fatal error 导致进程崩溃
        try {
            $reflection = new \ReflectionClass($className);
            $parentClass = $reflection->getParentClass();
            if ($parentClass) {
                $parentName = $parentClass->getName();
                if (!class_exists($parentName)) {
                    yield Sse::error("插件 Install.php 的父类 {$parentName} 不存在，请检查 use 导入路径", [], $sessionUuid);
                    return;
                }
            }
        } catch (\ReflectionException $e) {
            yield Sse::error('插件 Install.php 类反射检查失败：' . $e->getMessage(), [], $sessionUuid);
            return;
        }

        try {
            $version = $pluginConfig['version'] ?? '1.0.0';

            // 发送卸载开始事件
            yield Sse::progress('开始卸载插件', 10, ['plugin' => $code], $sessionUuid);

            // 派发卸载前事件
            (new PluginUninstalling($code, $version))->dispatch();

            // 平台卸载前: 占用清单阻断 (WP6)
            if ($tenantId === null) {
                /** @var \app\dao\plugin\TenantPluginDao $tenantPluginDao */
                $tenantPluginDao = Container::make(\app\dao\plugin\TenantPluginDao::class);
                $blockWhenOccupied = (bool)config('plugin.uninstall.block_when_occupied', true);
                if ($blockWhenOccupied) {
                    $count = $tenantPluginDao->countInstalledTenants($code);
                    if ($count > 0) {
                        $sample = array_slice($tenantPluginDao->getInstalledTenants($code), 0, 10);
                        $tenantIds = array_column($sample, 'tenant_id');
                        yield Sse::error(
                            "插件 [{$code}] 仍有 {$count} 个租户占用,无法卸载. 占用样例: " . implode(',', array_map('strval', $tenantIds)),
                            [
                                'plugin'     => $code,
                                'reason'     => 'occupied',
                                'occupants'  => $tenantIds,
                                'count'      => $count,
                                'hint'       => '请使用 cascadeUninstall 接口或设置 force=true 强制卸载',
                            ],
                            $sessionUuid
                        );
                        return;
                    }
                }
            }

            // 统一调用插件的 Install::uninstall() 方法（这是唯一的卸载入口）
            yield Sse::progress('执行插件卸载逻辑', 30, [], $sessionUuid);

            // 实例化并执行插件 Install 类（传入进度回调以支持在线模式反馈）
            $installInstance = new $className();

            // 设置安装上下文
            $context = $tenantId === null ? 'platform' : 'tenant';
            $installInstance->setContext($context);
            if ($tenantId !== null) {
                $installInstance->setTenantId($tenantId);
                // DB模式下设置租户数据库连接
                if (\core\business\tenant\context\TenantContext::getIsolationMode() === 'database') {
                    $connectionName = \core\business\tenant\context\TenantContext::getConnectionName();
                    if ($connectionName) {
                        $installInstance->setConnection($connectionName);
                    }
                }
            }

            // 创建进度记录临时文件
            $outputFile = $this->createProgressFile('uninstall', $sessionUuid);
            $progressCallback = $this->createProgressCallback($outputFile);

            // 设置进度回调和在线模式
            $installInstance->setProgressCallback($progressCallback);
            $installInstance->setOnlineMode(true);

            // 开启输出缓冲，捕获 Install::uninstall() 内部的 echo 输出
            $this->captureOutputWithProgress(function() use ($installInstance, $version) {
                $installInstance->uninstall($version);
            }, $outputFile);

            // 读取文件内容并 yield 所有日志
            yield from $this->yieldProgressLines($outputFile, 30, [], $sessionUuid);

            // 清理临时文件
            $this->cleanupProgressFile($outputFile);

            yield Sse::progress('插件卸载方法执行成功', 80, [], $sessionUuid);

            // 租户卸载：清理该租户插件菜单(由「租户安装模块」TenantMenuSyncService 负责, 软禁用 sys_menu)
            // 单体卸载的菜单清理已在 PluginInstall::clearMenus 阶段完成, 此处无需处理
            if ($tenantId !== null) {
                yield Sse::progress('清理租户插件菜单', 85, [], $sessionUuid);
                try {
                    /** @var \app\service\core\plugin\PluginLifecycleOrchestrator $orchestrator */
                    $orchestrator = Container::make(PluginLifecycleOrchestrator::class);
                    $orchestrator->clearTenantMenu($code, $version, (string)$tenantId);
                } catch (\Throwable $e) {
                    yield Sse::warning('租户插件菜单清理失败(可稍后重试): ' . $e->getMessage(), [], $sessionUuid);
                }
            }

            if ($tenantId === null) {
                // 平台卸载：删除 installed.php
                yield Sse::progress('删除安装配置', 90, [], $sessionUuid);
                $installedConfigPath = $this->plugin_path . DIRECTORY_SEPARATOR . $code . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'installed.php';
                if (is_file($installedConfigPath)) {
                    @unlink($installedConfigPath);
                }
            } else {
                // 租户卸载：删除 tenant_plugin 记录
                yield Sse::progress('删除租户插件记录', 90, [], $sessionUuid);
                /** @var \app\dao\plugin\TenantPluginDao $tenantPluginDao */
                $tenantPluginDao = \support\Container::make(\app\dao\plugin\TenantPluginDao::class);
                $record = $tenantPluginDao->findByTenantAndKey($tenantId, $code);
                if ($record) {
                    \app\model\plugin\TenantPluginInstall::where('tenant_id', $tenantId)
                        ->where('plugin_key', $code)
                        ->delete();
                    $record->delete();
                }
            }

            // 派发卸载后事件
            (new PluginUninstalled($code, $version))->dispatch();

            // 发送卸载完成事件
            yield Sse::completed('插件卸载成功', ['plugin' => $code], $sessionUuid);

        } catch (\Throwable $e) {
            // 发送卸载失败事件
            yield Sse::error('卸载失败：' . $e->getMessage(), [
                'exception' => get_class($e),
                'message'   => $e->getMessage(),
                'file'      => $e->getFile(),
                'line'      => $e->getLine(),
            ], $sessionUuid);
        }
    }

    /**
     * 删除插件（仅删除插件包）
     * 删除条件：
     * 1. 插件必须为已卸载状态（installed.php 不存在）
     * 2. 非官方插件（undeletable != true）
     *
     * @param string $code 插件编码
     *
     * @return void 删除成功返回 true
     * @throws \Exception
     */
    public function delete(string $code): void
    {
        // 1. 检查插件实体是否存在
        $pluginDir        = $this->plugin_path . DIRECTORY_SEPARATOR . $code;
        $pluginConfigFile = $pluginDir . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'info.php';

        if (!is_dir($pluginDir) || !is_file($pluginConfigFile)) {
            throw new \Exception('插件不存在，无法删除');
        }

        // 2. 检查插件配置
        $pluginConfig = $this->getPluginConfig($code);

        // 3. 检查是否为系统内置插件（不允许删除）
        if (isset($pluginConfig['uninstall']['undeletable']) && $pluginConfig['uninstall']['undeletable'] === true) {
            throw new \Exception('系统内置插件不允许删除');
        }

        // 4. 检查插件安装状态（installed.php 不存在）
        $installedConfigPath = $pluginDir . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'installed.php';
        $isInstalled         = is_file($installedConfigPath);

        if ($isInstalled) {
            throw new \Exception('插件已安装，请先卸载后再删除');
        }

        // 5. 检查插件是否提供 Install.php
        $className = "plugin\\{$code}\\Install";
        if (!class_exists($className)) {
            throw new \Exception("插件未提供 Install.php，无法进行删除");
        }

        try {
            $version = $pluginConfig['version'] ?? '1.0.0';

            // 实例化并执行插件 Install 类
            $installInstance = new $className();

            // 设置为 CLI 模式（不使用回调）
            $installInstance->setOnlineMode(false);

            // 执行删除
            $installInstance->delete($version);

            // 删除后，需要清理数据库中的插件记录
            $this->deletePluginRecord($code);
        } catch (\Throwable $e) {
            throw new \Exception('删除失败：' . $e->getMessage());
        }
    }

    /**
     * 删除插件数据库记录
     */
    protected function deletePluginRecord(string $code): void
    {
        try {
            /** @var PluginService $service */
            $service = Container::make(PluginService::class);
            $model   = $service->dao->query()->where('key', $code)->first();
            if ($model) {
                $model->delete();
            }
        } catch (\Throwable $e) {
            \support\Log::error('插件卸载删除记录失败: ' . $e->getMessage(), [
                'plugin'    => $code,
                'exception' => $e,
            ]);
        }
    }
}

