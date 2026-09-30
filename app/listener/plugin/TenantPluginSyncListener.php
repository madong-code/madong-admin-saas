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
namespace app\listener\plugin;

use app\event\plugin\PluginInstalled;
use app\event\plugin\PluginUninstalled;
use app\event\plugin\PluginUpdated;
use app\service\core\tenant\TenantMigrationService;
use core\foundation\base\BaseListener;
use support\Container;

/**
 * 租户插件同步监听器
 *
 * 监听插件安装/卸载/更新事件，自动同步到所有 database 模式租户
 *
 * 幂等契约: 当事件 context === 'orchestrator' 时跳过自动扇出,
 * 由 PluginLifecycleOrchestrator 自行显式分批派发(避免与编排器重复执行)
 */
class TenantPluginSyncListener extends BaseListener
{
    protected function process($event): void
    {
        // 编排器入口已显式处理租户扇出,此处幂等跳过,防止双重同步
        if (($event->extra['context'] ?? 'legacy') === 'orchestrator') {
            return;
        }

        /** @var TenantMigrationService $service */
        $service = Container::make(TenantMigrationService::class);

        $action = match (true) {
            $event instanceof PluginInstalled => 'install',
            $event instanceof PluginUninstalled => 'uninstall',
            $event instanceof PluginUpdated => 'update',
            default => 'install',
        };

        $service->syncPluginToTenants($event->code, $event->version, $action);
    }
}
