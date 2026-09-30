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
namespace app\adminapi\schema\request\content\message;


use app\schema\request\BaseFormRequest;
use OpenApi\Attributes as OA;
use WebmanTech\DTO\Attributes\ValidationRules;

#[OA\Schema(
    title: '消息表单',
    description: '消息创建和编辑接口共用的表单请求参数'
)]
class MessageFormRequest extends BaseFormRequest
{
    #[OA\Property(
        property: 'type',
        description: '消息类型（如公告、通知等）',
        type: 'string',
        example: 'announcement',
        nullable: false
    )]
    #[ValidationRules(rules: 'required|string|max:50')]
    public string $type;

    #[OA\Property(
        property: 'title',
        description: '消息标题',
        type: 'string',
        example: '系统升级维护通知',
        nullable: false
    )]
    #[ValidationRules(rules: 'required|string|max:100')]
    public string $title;

    #[OA\Property(
        property: 'content',
        description: '消息内容',
        type: 'string',
        example: '系统将于2023-12-31 23:00进行维护升级，预计持续2小时',
        nullable: false
    )]
    #[ValidationRules(rules: 'required|string')]
    public string $content;

    #[OA\Property(
        property: 'sender_id',
        description: '发送者ID（关联管理员表）',
        type: 'string',
        example: '987654321098765432',
        nullable: false
    )]
    #[ValidationRules(rules: 'required|string')]
    public string $sender_id;

    #[OA\Property(
        property: 'receiver_id',
        description: '接收者ID（关联管理员表，0表示全员）',
        type: 'string',
        example: '567890123456789012',
        nullable: false
    )]
    #[ValidationRules(rules: 'required|string')]
    public string $receiver_id;

    #[OA\Property(
        property: 'status',
        description: '消息状态（0=未读，1=已读）',
        type: 'integer',
        enum: [0, 1],
        example: 0,
        nullable: false
    )]
    #[ValidationRules(rules: 'required|in:0,1')]
    public int $status;

    #[OA\Property(
        property: 'priority',
        description: '优先级（1=低，2=中，3=高）',
        type: 'integer',
        example: 3,
        nullable: false
    )]
    #[ValidationRules(rules: 'required|integer|between:1,3')]
    public int $priority;

    #[OA\Property(
        property: 'channel',
        description: '发送渠道（如system=系统内，email=邮件）',
        type: 'string',
        example: 'system',
        nullable: false
    )]
    #[ValidationRules(rules: 'required|string|max:20')]
    public string $channel;

    #[OA\Property(
        property: 'related_id',
        description: '关联业务ID（如订单ID、任务ID）',
        type: 'string',
        example: '456789012345678901',
        nullable: true
    )]
    #[ValidationRules(rules: 'string|max:32|nullable')]
    public ?string $related_id = null;

    #[OA\Property(
        property: 'related_type',
        description: '关联业务类型（如order=订单，task=任务）',
        type: 'string',
        example: 'order',
        nullable: true
    )]
    #[ValidationRules(rules: 'string|max:20|nullable')]
    public ?string $related_type = null;

    #[OA\Property(
        property: 'action_url',
        description: '操作跳转URL',
        type: 'string',
        example: '/system/notice/1',
        nullable: true
    )]
    #[ValidationRules(rules: 'string|max:255|nullable')]
    public ?string $action_url = null;

    #[OA\Property(
        property: 'action_params',
        description: '操作参数（JSON字符串）',
        type: 'string',
        example: '{"id":1,"type":"detail"}',
        nullable: true
    )]
    #[ValidationRules(rules: 'string|nullable')]
    public ?string $action_params = null;

    #[OA\Property(
        property: 'expired_at',
        description: '过期时间',
        type: 'string',
        format: 'date-time',
        example: '2023-12-31T23:59:59Z',
        nullable: true
    )]
    #[ValidationRules(rules: 'date_format:Y-m-d H:i:s|nullable')]
    public ?string $expired_at = null;
}
