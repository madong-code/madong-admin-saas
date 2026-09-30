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

use app\model\content\message\Message;
use core\infrastructure\logger\Logger;
use core\communication\notify\enum\PushClientType;
use core\io\uuid\UUIDGenerator;
use InvalidArgumentException;
use madong\helper\Arr;
use Webman\Push\Api;

final class NotificationService
{
    private const DEFAULT_EXPIRE_DAYS = 30;

    protected ?Api $pushApi = null;
    protected Message $messageModel;

    public function __construct()
    {
        $config             = $this->getConfig();
        $this->pushApi      = new Api(
            $config['api'],
            $config['app_key'],
            $config['app_secret']
        );
        $this->messageModel = new Message();
    }

    /**
     * 推送消息并记录（支持单条/批量）
     *
     * @param PushClientType   $clientType     客户端类型枚举值
     * @param string           $businessModule 业务模块标识
     * @param string           $tenantId       租户ID
     * @param string|int|array $userIds        接收者ID(单个ID或ID数组)
     * @param string           $event          事件类型
     * @param array            $data           推送数据(将直接传递给客户端)
     * @param array            $messageData    消息记录数据，包含:
     *                                         - 'title' (string): 消息标题
     *                                         - 'content' (string): 消息内容
     *                                         - 'type' (string): 消息类型(默认为事件类型)
     *                                         - 'priority' (int): 消息优先级
     *                                         - 'related_id' (string|null): 关联ID
     *                                         - 'expired_at' (int|null): 过期时间戳(默认为null，使用默认过期天数)
     *                                         - 'message_uuid'(string|null):消息uuid 区别
     * @param string|null      $socketId       指定socket连接ID
     *
     * @return array 返回结果数组包含:
     *        - 'push_count' (int): 成功推送的频道数量
     *        - 'messages' (array): 创建的消息对象数组(包含完整消息详情)
     */
    public function sendAndRecord(
        PushClientType   $clientType,
        string           $businessModule,
        string           $tenantId,
        string|int|array $userIds,
        string           $event = 'message',
        array            $data = [],
        array            $messageData = [],
        ?string          $socketId = null
    ): array
    {

        $userIds = Arr::normalize($userIds);
        $result  = [
            'push_count' => 0,
            'messages'   => [], // 直接返回创建的消息对象
        ];

        try {
            // 1. 批量创建消息记录
            // 使用合并后的消息数据创建记录，包含事件类型、租户ID、频道和过期时间
            $messages = $this->createMessages(
                $userIds,
                array_merge($messageData, [
                    'type'       => $event,
                    'tenant_id'  => $tenantId,
                    'channel'    => $this->buildChannel($clientType, $businessModule, $tenantId, null),
                    'expired_at' => $this->calculateExpireTimestamp($messageData['expired_at'] ?? null),
                ]),
            );

            // 2. 准备推送数据（直接使用已创建的消息对象）
            // 将消息格式化为适合推送的格式后附加到推送数据中
            $pushData = array_merge($data, [
                'messages' => $this->formatMessagesForPush($messages),
            ]);

            // 3. 执行推送
            $channels = array_map(
                fn($userId) => $this->buildChannel($clientType, $businessModule, $tenantId, $userId),
                $userIds
            );

            // 批量单条消息批量推送异常等官方修复
            $this->pushApi->trigger($channels, $event, $pushData, $socketId);

            $result['push_count'] = count($channels);
            $result['messages']   = $messages;

            Logger::debug("推送成功", [
                'channels'      => $channels,
                'message_count' => count($messages),
            ]);

        } catch (\Throwable $e) {
            Logger::error("推送失败: " . $e->getMessage(), [
                'exception' => $e,
                'user_ids'  => $userIds,
            ]);
        }

        return $result;
    }

    /**
     * 批量推送不同业务消息（带记录）
     *
     * @param PushClientType $clientType  客户端类型枚举值
     * @param string         $tenantId    租户ID
     * @param array          $messages    批量消息数组，每个元素包含以下字段：
     *                                    - 'module' (string): 业务模块标识
     *                                    - 'userIds' (string|array): 接收者ID(单个ID或ID数组)
     *                                    - 'event' (string): 事件类型
     *                                    - 'data' (array, optional): 推送数据，默认为空数组
     *                                    - 'title' (string, optional): 消息标题，默认为空字符串
     *                                    - 'content' (string, optional): 消息内容，默认为空字符串
     *                                    - 'message_type' (string, optional): 消息类型，默认为事件类型(event)
     *                                    - 'priority' (int, optional): 消息优先级，默认为0
     *                                    - 'related_id' (string|null, optional): 关联ID，默认为null
     *                                    - 'expired_at' (int|null, optional): 过期时间戳，默认为null(使用默认过期天数)
     *                                    - 'message_uuid'(string|null):消息uuid 区别公共重复推送
     *
     * @return array 返回批量推送结果数组，每个元素包含:
     *        - 'push_count' (int): 成功推送数量
     *        - 'message_ids' (array): 创建的消息ID数组
     *        - 'message_details' (array): 完整的消息记录详情
     */
    public function batchSend(
        PushClientType $clientType,
        string         $tenantId,
        array          $messages
    ): array
    {
        $results = [];

        foreach ($messages as $msg) {
            $results[] = $this->sendAndRecord(
                $clientType,
                $msg['module'],
                $tenantId,
                $msg['receiver_id'],
                $msg['event'] ?? 'message',
                $msg['data'] ?? [],
                [
                    'title'        => $msg['title'] ?? '',
                    'content'      => $msg['content'] ?? '',
                    'type'         => $msg['message_type'] ?? $msg['event'],
                    'priority'     => $msg['priority'] ?? 0,
                    'related_id'   => $msg['related_id'] ?? null,
                    'expired_at'   => $msg['expired_at'] ?? null,
                    'message_uuid' => $msg['message_uuid'] ?? null,
                ]
            );
        }
        return $results;
    }

    /**
     * 仅推送消息（不记录）
     */
    public function pushOnly(
        PushClientType   $clientType,
        string           $businessModule,
        string           $tenantId,
        string|int|array $userIds,
        string           $event = 'message',
        array            $data = [],
        array            $messages = [],
        ?string          $socketId = null
    ): int
    {
        $userIds  = Arr::normalize($userIds);
        $channels = array_map(
            fn($userId) => $this->buildChannel($clientType, $businessModule, $tenantId, $userId),
            $userIds
        );

        $pushData = array_merge($data, [
            'messages' => $this->formatMessagesForPush($messages),
        ]);

        return $this->pushApi->trigger($channels, $event, $pushData, $socketId);
    }

    /**
     * 仅记录消息（不推送）
     */
    public function recordOnly(
        array $userIds,
        array $messageData
    ): array
    {
        return $this->createMessages(Arr::normalize($userIds), $messageData);
    }

    /**
     * 批量创建消息记录（返回完整消息对象）
     */
    private function createMessages(array $receiverIds, array $data): array
    {
        $defaults = [
            'status'     => 'unread',
            'priority'   => 3,
            'expired_at' => $data['expired_at'] ?? $this->calculateExpireTimestamp(self::DEFAULT_EXPIRE_DAYS),
        ];

        $messages = [];
        foreach ($receiverIds as $receiverId) {
                 $message    = $this->messageModel->create(array_merge(
                $defaults,
                $data,
                ['message_uuid' => $this->generateMessageUuid($data['message_uuid'] ?? null)],
                ['receiver_id' => $receiverId]
            ));
            $messages[] = $message->toArray(); // 直接返回模型数据
        }
        return $messages;
    }

    /**
     * 生成唯一的 UUID（如果未提供则自动生成）
     *
     * @param string|null $uuid 可选的外部 UUID（如果为 null 则自动生成）
     *
     * @return string 有效的 UUID
     * @throws \Exception 如果 UUID 生成失败
     */
    private function generateMessageUuid(?string $uuid = null): string
    {
        if (empty($uuid)) {
            try {
                return UUIDGenerator::generate(); // 假设返回字符串 UUID
            } catch (\Exception $e) {
                throw new \Exception("Failed to generate UUID: " . $e->getMessage());
            }
        }
        return $uuid;
    }

    /**
     * 计算过期时间戳
     */
    private function calculateExpireTimestamp(int|null $expireDays): int
    {
        if ($expireDays === null) {
            $expireDays = self::DEFAULT_EXPIRE_DAYS;
        }
        return time() + ($expireDays * 86400); // 86400秒=1天
    }

    /**
     * 格式化消息数据用于推送
     */
    private function formatMessagesForPush(array $messages): array
    {
        return array_map(function ($message) {
            return $message;
        }, $messages);
    }

    /**
     * 生成标准化频道名称
     */
    public function buildChannel(
        PushClientType $clientType,
        string         $businessModule,
        string         $tenantId,
        ?string        $userId = null
    ): string
    {
        // 业务模块校验
        if (!preg_match('/^[a-z0-9_]+$/', $businessModule)) {
            throw new InvalidArgumentException('业务模块只能包含小写字母、数字和下划线');
        }

        return implode('-', [
            $clientType->value,
            $businessModule,
            $tenantId,
            $userId ?: '*',
        ]);
    }

    /**
     * 获取配置
     *
     * @return array
     */
    private function getConfig(): array
    {
        $config = config('core.communication.notify.webman-push');
        if (empty($config)) {
            throw new \RuntimeException('消息推送配置未定义');
        }
        return $config;
    }
}

