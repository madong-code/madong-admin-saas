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
namespace core\communication\notify;

use core\communication\notify\enum\PushClientType;
use support\Container;


/**
 * 消息通知服务门面
 *
 * 推荐直接注入 NotificationService 使用，保持调用方无状态。
 * 保留此门面作为便捷入口，每次调用独立从容器获取服务实例，
 * 避免进程内单例污染。
 *
 * 用法：
 * ```php
 * // 推荐：注入 NotificationService
 * $notification->sendAndRecord(...);
 *
 * // 便捷：门面方式（不缓存实例）
 * Notification::sendAndRecord(...);
 * ```
 *
 * @method static array sendAndRecord(PushClientType $clientType, string $businessModule, string $tenantId, string|int|array $userIds, string $event, array $data = [], array $messageData = [], ?string $socketId = null) 推送消息并记录（支持单条/批量）
 * @method static array batchSend(PushClientType $clientType, string $tenantId, array $messages) 批量推送不同业务消息
 * @method static array pushOnly(PushClientType $clientType, string $businessModule, string $tenantId, string|int|array $userIds, string $event = 'message', array $data = [], $messages = [], ?string $socketId = null) 仅推送消息（不记录）
 * @method static array recordOnly(array $userIds, array $messageData)  仅记录消息（不推送）
 *
 * @see \core\communication\notify\NotificationService 底层服务实现类
 */
final class Notification
{
    /**
     * 私有构造方法，禁止外部实例化
     */
    private function __construct()
    {
    }

    /**
     * 从容器获取服务实例（每次调用新实例，不缓存，避免进程内污染）
     *
     * @return NotificationService
     */
    private static function instance(): NotificationService
    {
        return Container::make(NotificationService::class);
    }

    /**
     * 静态方法调用代理
     *
     * @param string $method 调用的方法名
     * @param array  $args   方法参数
     *
     * @return mixed 方法调用结果
     */
    public static function __callStatic(string $method, array $args)
    {
        return self::instance()->$method(...$args);
    }


}
