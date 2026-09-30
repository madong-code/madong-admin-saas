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

namespace app\queue\redis;

use app\adminapi\event\content\MessagePushEvent;
use app\enum\system\BusinessPlatform;
use app\enum\system\MessageEvent;
use app\enum\system\MessageType;
use app\service\system\SysAdminService;
use core\infrastructure\logger\Logger;
use core\communication\notify\enum\PushClientType;
use core\foundation\base\BaseQueueConsumer;

/**
 * 推送公告-后台
 *
 * @author Mr.April
 * @since  1.0
 */
class AdminAnnouncementPushConsumer extends BaseQueueConsumer
{
    public string $queue = 'admin-announcement-push';

    protected function handle(array $data): void
    {
        Logger::debug('公告推送开始', $data);

        // 1. 验证必要参数
        $this->validateData($data);

        // 2. 获取目标用户ID列表
        $adminIds = $this->getTargetAdminIds($data['uuid'] ?? null);

        // 3. 构建并发送通知
        $this->sendNotifications($adminIds, $data);

        Logger::debug("公告推送完成: {$data['title']}");
    }

    /**
     * 验证消息数据
     *
     * @param array $data
     *
     * @throws \InvalidArgumentException
     */
    private function validateData(array $data): void
    {
        $requiredFields = ['id', 'title', 'content'];
        foreach ($requiredFields as $field) {
            if (empty($data[$field])) {
                throw new \InvalidArgumentException("缺少必要字段: {$field}");
            }
        }
    }

    /**
     * 获取目标管理员ID列表
     *
     * @param string|null $uuid
     *
     * @return array
     */
    private function getTargetAdminIds(?string $uuid): array
    {
        $userService = new SysAdminService();
        $query       = $userService->getModel()
            ->where('enabled', 1);

        // 只有当uuid不为空时才检查消息
        if (!empty($uuid)) {
            $query->where(function ($q) use ($uuid) {
                $q->whereDoesntHave('message', function ($subQuery) use ($uuid) {
                    $subQuery->where('message_uuid', $uuid);
                })
                    ->orWhereHas('message', function ($subQuery) {
                        $subQuery->whereNull('message_uuid');
                    });
            });
        }

        return $query->pluck('id')
            ->unique()
            ->values()
            ->toArray();
    }

    /**
     * 发送通知
     *
     * @param array      $adminIds
     * @param array      $data
     */
    private function sendNotifications(array $adminIds, array $data): void
    {
        if (empty($adminIds)) {
            Logger::warning('没有符合条件的目标用户');
            return;
        }

        foreach ($adminIds as $id) {
            $messageData = [
                'title'        => $data['title'],
                'content'      => $data['content'],
                'message_type' => MessageEvent::DEFAULT->value,
                'priority'     => 1,
                'related_id'   => $data['id'] ?? '',
                'related_type' => MessageType::ANNOUNCEMENT->value,
                'expired_at'   => time() + 86400 * 7,
                'message_uuid' => $data['uuid'] ?? null,
                'category_key' => 'announcement',
            ];

            (new MessagePushEvent(
                PushClientType::BACKEND,
                BusinessPlatform::ADMIN->value,
                '*',
                $id,
                MessageEvent::DEFAULT->value,
                [],
                $messageData,
                null,
                'announcement'
            ))->dispatch();
        }
    }
}
