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
namespace app\adminapi\listener\content;

use app\service\admin\content\message\NotifyService;
use core\business\tenant\context\TenantContext;
use core\business\tenant\TenantConnectionManager;
use core\communication\notify\NotificationService;
use core\foundation\base\BaseListener;
use core\infrastructure\logger\Logger;

/**
 * 消息推送事件监听器
 *
 * 负责调用 MessageNotifyService::send() 实现推送，
 * 内部包含 subscribeService->isSubscribed() 订阅过滤逻辑。
 *
 * ⚠️ 在调用前主动初始化租户上下文，确保：
 * - 字段隔离：message 记录的 tenant_id 写入正确
 * - 库隔离（database mode）：切换到目标租户的 DB 连接后再写入
 * - 通配符（'*'）场景：不做任何切换，沿用当前的连接上下文
 */
class MessagePushListener extends BaseListener
{
    private ?NotificationService $notificationService = null;
    private ?NotifyService $notifyService = null;

    protected function process($event): void
    {
        Logger::info('消息推送事件', [
            'scene'    => $event->scene,
            'module'   => $event->businessModule,
            'tenantId' => $event->tenantId,
            'count'    => is_array($event->userIds) ? count($event->userIds) : 1,
        ]);

        // 保存当前租户上下文，执行完后恢复
        $savedTenantId = TenantContext::getTenantId();
        $contextRestored = false;

        try {
            // 若事件指定了具体租户且上下文尚未设置，则初始化
            if ($event->tenantId !== null && $event->tenantId !== '*' && !TenantContext::isInitialized()) {
                if (!TenantContext::isSingleMode()) {
                    // 加载完整的租户信息（含 database_mode），按隔离模式设置连接
                    TenantContext::setTenant($event->tenantId);
                    $tenantInfo = TenantContext::getTenantInfo();
                    $dbMode = $tenantInfo['database_mode'] ?? 'field';
                    if ($dbMode === 'database') {
                        // 库隔离：切换到租户独立数据库连接
                        TenantConnectionManager::setCurrentConnection($event->tenantId);
                    } else {
                        // 字段隔离：保持主库，仅设置隔离模式
                        TenantContext::setIsolationMode('field');
                    }
                    $contextRestored = true;
                }
            }

            if ($event->isForce) {
                // 强制推送：跳过订阅过滤，直接发送并记录
                $notificationService = $this->getNotificationService();
                $result = $notificationService->sendAndRecord(
                    $event->clientType,
                    $event->businessModule,
                    $event->tenantId,
                    $event->userIds,
                    $event->event,
                    $event->data,
                    $event->messageData,
                    $event->socketId
                );
            } else {
                $notifyService = $this->getNotifyService();
                $result  = $notifyService->send(
                    $event->clientType,
                    $event->businessModule,
                    $event->tenantId,
                    $event->userIds,
                    $event->event,
                    $event->data,
                    $event->messageData,
                    $event->socketId
                );
            }

            Logger::info('消息推送结果', [
                'push_count' => $result['push_count'] ?? 0,
                'scene'      => $event->scene,
            ]);
        } catch (\Throwable $e) {
            Logger::error('消息推送失败', [
                'error'  => $e->getMessage(),
                'scene'  => $event->scene,
                'tenant' => $event->tenantId,
            ]);
        } finally {
            // 恢复租户上下文（仅在自己初始化的情况下）
            if ($contextRestored) {
                TenantContext::init($savedTenantId);
            }
        }
    }

    /**
     * 懒加载 NotificationService
     * 避免 PHP 原生回调创建实例时无法注入
     */
    private function getNotificationService(): NotificationService
    {
        if ($this->notificationService === null) {
            $this->notificationService = \support\Container::get(NotificationService::class);
        }
        return $this->notificationService;
    }

    /**
     * 懒加载 NotifyService
     * 避免 PHP 原生回调创建实例时无法注入
     */
    private function getNotifyService(): NotifyService
    {
        if ($this->notifyService === null) {
            $this->notifyService = \support\Container::get(NotifyService::class);
        }
        return $this->notifyService;
    }
}
