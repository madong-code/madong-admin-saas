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
use core\infrastructure\logger\Logger;
use madong\helper\Arr;
use Webman\Push\Api;

/**
 * 菜单徽标推送服务（SaaS 租户感知版）
 *
 * 通过 WebSocket 向指定用户推送菜单徽标变更事件，前端监听 'menu_badge' 事件后更新徽标状态。
 *
 * 频道命名与 NotificationService::buildChannel() 保持一致：
 *   backend-admin-{tenantId}-{userId}      租户管理端
 *   backend-platform-*-{userId}            平台运营端（平台无租户，tenantId 传 '*'）
 *
 * 使用示例:
 *   $service = new MenuBadgePushService();
 *   $service->pushBadgeUpdate($tenantId, $userId, '/content/message', '5', 'primary');
 *   $service->pushBadgeClear($tenantId, $userId, '/content/message');
 */
final class MenuBadgePushService
{
    private const BUSINESS_MODULE = 'admin';

    /** 推送事件名（前端 channel.on('menu_badge') 监听） */
    private const EVENT_NAME = 'menu_badge';

    /**
     * 推送客户端（懒建：构造函数零 IO）
     *
     * 项目既有教训：Swow 协程下构造期阻塞 IO 会引发循环依赖/连接串扰，
     * 故延迟到首次真正推送时再创建 Api 实例。
     */
    private ?Api $pushApi = null;

    /**
     * 单条更新
     *
     * @param string           $tenantId 租户ID；平台端传 '*'
     * @param int|string|array $userIds  用户ID（单个或数组）
     * @param string           $path     菜单路径
     * @param string           $badge    徽标文本（空字符串 + type=dot 表示仅圆点）
     * @param string           $variant  徽标颜色
     * @param string           $type     徽标类型 (normal|dot)
     *
     * @return int 成功推送的频道数量
     */
    public function pushBadgeUpdate(
        string           $tenantId,
        int|string|array $userIds,
        string           $path,
        string           $badge = '',
        string           $variant = 'primary',
        string           $type = 'normal'
    ): int {
        return $this->trigger($this->buildChannels($tenantId, Arr::normalize($userIds)), [
            'type' => 'update',
            'data' => [
                'path'           => $path,
                'badge'          => $badge,
                'badge_type'     => $type,
                'badge_variants' => $variant,
            ],
        ]);
    }

    /**
     * 批量更新
     *
     * @param string           $tenantId 租户ID；平台端传 '*'
     * @param int|string|array $userIds  用户ID（单个或数组）
     * @param array            $updates  徽标数据数组，每项为 snake_case：
     *                                   [['path'=>..,'badge'=>..,'badge_type'=>..,'badge_variants'=>..], ...]
     *
     * @return int
     */
    public function pushBadgeBatchUpdate(string $tenantId, int|string|array $userIds, array $updates): int
    {
        return $this->trigger($this->buildChannels($tenantId, Arr::normalize($userIds)), [
            'type' => 'batch_update',
            'data' => $updates,
        ]);
    }

    /**
     * 清除指定菜单的徽标
     *
     * @param string           $tenantId
     * @param int|string|array $userIds
     * @param string           $path
     *
     * @return int
     */
    public function pushBadgeClear(string $tenantId, int|string|array $userIds, string $path): int
    {
        return $this->trigger($this->buildChannels($tenantId, Arr::normalize($userIds)), [
            'type' => 'clear',
            'data' => ['path' => $path],
        ]);
    }

    /**
     * 重置用户所有菜单徽标
     *
     * @param string           $tenantId
     * @param int|string|array $userIds
     *
     * @return int
     */
    public function pushBadgeReset(string $tenantId, int|string|array $userIds): int
    {
        return $this->trigger($this->buildChannels($tenantId, Arr::normalize($userIds)), [
            'type' => 'reset',
            'data' => [],
        ]);
    }

    /**
     * 构建用户频道列表
     *
     * @param string $tenantId 租户ID；平台端传 '*'
     * @param array  $userIds  用户ID数组
     *
     * @return array
     */
    private function buildChannels(string $tenantId, array $userIds): array
    {
        return array_map(
            fn($userId) => implode('-', [
                PushClientType::BACKEND->value,
                self::BUSINESS_MODULE,
                $tenantId !== '' ? $tenantId : '*',
                $userId,
            ]),
            $userIds
        );
    }

    /**
     * 触发推送
     *
     * @param array $channels 频道列表
     * @param array $data     推送数据
     *
     * @return int
     */
    private function trigger(array $channels, array $data): int
    {
        try {
            $result = $this->api()->trigger($channels, self::EVENT_NAME, $data);
            Logger::debug('菜单徽标推送成功', [
                'channels' => $channels,
                'type'     => $data['type'] ?? '',
                'result'   => $result,
            ]);

            return is_int($result) ? $result : ($result ? 1 : 0);
        } catch (\Throwable $e) {
            Logger::error('菜单徽标推送失败: ' . $e->getMessage(), [
                'channels' => $channels,
                'type'     => $data['type'] ?? '',
            ]);

            return 0;
        }
    }

    /**
     * 获取推送客户端（懒建）
     *
     * @return Api
     */
    private function api(): Api
    {
        if ($this->pushApi === null) {
            $config = config('core.communication.notify.webman-push');
            if (empty($config)) {
                throw new \RuntimeException('推送配置未定义');
            }
            $this->pushApi = new Api($config['api'], $config['app_key'], $config['app_secret']);
        }

        return $this->pushApi;
    }
}