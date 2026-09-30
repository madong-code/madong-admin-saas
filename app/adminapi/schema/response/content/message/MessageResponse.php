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

namespace app\adminapi\schema\response\content\message;

use OpenApi\Attributes as OA;

#[OA\Schema(
    title: '消息详情响应模型',
    description: '消息详情接口的返回数据结构'
)]
class MessageResponse
{
    #[OA\Property(
        property: 'id',
        description: '消息ID（雪花ID）',
        type: 'string',
        example: '245329792713883648'
    )]
    public string $id;

    #[OA\Property(
        property: 'type',
        description: '消息类型',
        type: 'string',
        enum: ['message', 'notification', 'broadcast'],
        example: 'message'
    )]
    public string $type;

    #[OA\Property(
        property: 'title',
        description: '消息标题',
        type: 'string',
        example: '111111111111111'
    )]
    public string $title;

    #[OA\Property(
        property: 'content',
        description: '消息内容',
        type: 'string',
        example: '222222222222222222222'
    )]
    public string $content;

    #[OA\Property(
        property: 'sender_id',
        description: '发送者ID（0表示系统）',
        type: 'integer',
        example: 0
    )]
    public int $sender_id;

    #[OA\Property(
        property: 'receiver_id',
        description: '接收者ID',
        type: 'integer',
        example: 2
    )]
    public int $receiver_id;

    #[OA\Property(
        property: 'status',
        description: '阅读状态',
        type: 'string',
        enum: ['read', 'unread'],
        example: 'read'
    )]
    public string $status;

    #[OA\Property(
        property: 'priority',
        description: '优先级（1普通 2重要 3紧急）',
        type: 'integer',
        enum: [1, 2, 3],
        example: 1
    )]
    public int $priority;

    #[OA\Property(
        property: 'channel',
        description: '推送渠道',
        type: 'string',
        example: 'backend-admin-1-*'
    )]
    public string $channel;

    #[OA\Property(
        property: 'related_id',
        description: '关联业务ID',
        type: 'string',
        example: '234161405304516608',
        nullable: true
    )]
    public ?string $related_id;

    #[OA\Property(
        property: 'related_type',
        description: '关联业务类型',
        type: 'string',
        example: '',
        nullable: true
    )]
    public ?string $related_type;

    #[OA\Property(
        property: 'jump_params',
        description: '跳转参数',
        type: 'object',
        example: null,
        nullable: true
    )]
    public ?array $jump_params;

    #[OA\Property(
        property: 'message_uuid',
        description: '消息唯一标识',
        type: 'string',
        example: 'a6a0ff4f-81dc-c033-083e-99611865251c'
    )]
    public string $message_uuid;

    #[OA\Property(
        property: 'created_at',
        description: '创建时间（UTC）',
        type: 'string',
        format: 'date-time',
        example: '2025-11-07T15:33:00.000000Z'
    )]
    public string $created_at;

    #[OA\Property(
        property: 'expired_at',
        description: '过期时间戳',
        type: 'integer',
        example: 152336572961580
    )]
    public int $expired_at;

    #[OA\Property(
        property: 'read_at',
        description: '阅读时间戳',
        type: 'integer',
        example: 1762610591,
        nullable: true
    )]
    public ?int $read_at;

    #[OA\Property(
        property: 'updated_at',
        description: '更新时间（UTC）',
        type: 'string',
        format: 'date-time',
        example: '2025-11-08T14:03:11.000000Z'
    )]
    public string $updated_at;

    #[OA\Property(
        property: 'deleted_at',
        description: '删除时间',
        type: 'string',
        format: 'date-time',
        example: null,
        nullable: true
    )]
    public ?string $deleted_at;

    #[OA\Property(
        property: 'created_date',
        description: '创建时间（本地格式化）',
        type: 'string',
        example: '2025-11-07 23:33:00'
    )]
    public string $created_date;

    #[OA\Property(
        property: 'updated_date',
        description: '更新时间（本地格式化）',
        type: 'string',
        example: '2025-11-08 22:03:11'
    )]
    public string $updated_date;

    #[OA\Property(
        property: 'read_date',
        description: '阅读时间（本地格式化）',
        type: 'string',
        example: '2025-11-08 22:03:11',
        nullable: true
    )]
    public ?string $read_date;

    #[OA\Property(
        property: 'sender',
        description: '发送者信息',
        type: 'object',
        example: null,
        nullable: true
    )]
    public ?array $sender;
}
