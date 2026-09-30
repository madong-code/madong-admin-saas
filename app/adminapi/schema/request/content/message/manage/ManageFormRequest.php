<?php
declare(strict_types=1);

/**
 *+------------------
 * madong - 消息管理表单请求 DTO
 *+------------------
 * Copyright (c) https://gitee.com/motion-code  All rights reserved.
 *+------------------
 * Author: Mr. April (405784684@qq.com)
 *+------------------
 * Official Website: https://madong.tech
 */

namespace app\adminapi\schema\request\content\message\manage;

use app\schema\request\BaseFormRequest;
use OpenApi\Attributes as OA;
use WebmanTech\DTO\Attributes\ValidationRules;

#[OA\Schema(
    title: '消息管理表单',
    description: '消息管理创建和编辑共用的表单请求参数（含模板字段）'
)]
class ManageFormRequest extends BaseFormRequest
{
    // ========== 消息定义字段 ==========
    #[OA\Property(property: 'category_id', description: '所属分类ID', type: 'string', example: '123456789012345678')]
    #[ValidationRules(rules: 'required')]
    public string $category_id;

    #[OA\Property(property: 'key', description: '消息标识（同一分类内唯一）', type: 'string', example: 'order_paid')]
    #[ValidationRules(rules: 'required|string|max:50')]
    public string $key;

    #[OA\Property(property: 'name', description: '消息名称', type: 'string', example: '订单支付通知')]
    #[ValidationRules(rules: 'required|string|max:100')]
    public string $name;

    #[OA\Property(property: 'description', description: '描述', type: 'string', example: '订单支付成功后发送通知', nullable: true)]
    #[ValidationRules(rules: 'string|max:255|nullable')]
    public ?string $description = null;

    #[OA\Property(property: 'default_on', description: '默认是否开启订阅', type: 'integer', enum: [0, 1], example: 1, nullable: true)]
    #[ValidationRules(rules: 'in:0,1|nullable')]
    public ?int $default_on = 1;

    #[OA\Property(property: 'nav_type', description: '导航类型', type: 'string', enum: ['router', 'url', 'none'], example: 'router', nullable: true)]
    #[ValidationRules(rules: 'string|max:20|nullable')]
    public ?string $nav_type = null;

    #[OA\Property(property: 'nav_value', description: '导航值', type: 'string', example: '/order/detail', nullable: true)]
    #[ValidationRules(rules: 'string|max:500|nullable')]
    public ?string $nav_value = null;

    #[OA\Property(property: 'sort', description: '排序', type: 'integer', example: 0, nullable: true)]
    #[ValidationRules(rules: 'integer|nullable')]
    public ?int $sort = 0;

    #[OA\Property(property: 'enabled', description: '是否启用', type: 'integer', enum: [0, 1], example: 1, nullable: true)]
    #[ValidationRules(rules: 'in:0,1|nullable')]
    public ?int $enabled = 1;

    // ========== 消息模板字段 ==========
    #[OA\Property(property: 'type', description: '模板类型', type: 'string', enum: ['system', 'sms', 'email', 'webhook'], example: 'system')]
    #[ValidationRules(rules: 'required|string|max:30')]
    public string $type;

    #[OA\Property(property: 'template_id', description: '外部模板ID', type: 'string', example: 'SMS_123456', nullable: true)]
    #[ValidationRules(rules: 'string|max:100|nullable')]
    public ?string $template_id = null;

    #[OA\Property(property: 'title', description: '消息标题(模板)', type: 'string', example: '订单支付成功通知', nullable: true)]
    #[ValidationRules(rules: 'string|max:200|nullable')]
    public ?string $title = null;

    #[OA\Property(property: 'content_template', description: '内容模板', type: 'string', example: '您的订单 {order_no} 已支付成功', nullable: true)]
    #[ValidationRules(rules: 'string|nullable')]
    public ?string $content_template = null;

    #[OA\Property(property: 'button_template', description: '按钮模板JSON', type: 'string', example: '{"text":"查看详情","url":"/order/"}', nullable: true)]
    #[ValidationRules(rules: 'string|max:500|nullable')]
    public ?string $button_template = null;

    #[OA\Property(property: 'url', description: 'PC端跳转链接', type: 'string', example: '/order/detail', nullable: true)]
    #[ValidationRules(rules: 'string|max:500|nullable')]
    public ?string $url = null;

    #[OA\Property(property: 'uni_url', description: '移动端跳转链接', type: 'string', example: '/pages/order/detail', nullable: true)]
    #[ValidationRules(rules: 'string|max:500|nullable')]
    public ?string $uni_url = null;

    #[OA\Property(property: 'webhook_url', description: 'Webhook地址', type: 'string', example: 'https://webhook.example.com/notify', nullable: true)]
    #[ValidationRules(rules: 'string|max:500|nullable')]
    public ?string $webhook_url = null;

    #[OA\Property(property: 'image', description: '消息图片', type: 'string', example: 'https://example.com/image.png', nullable: true)]
    #[ValidationRules(rules: 'string|max:255|nullable')]
    public ?string $image = null;

    #[OA\Property(property: 'push_rule', description: '推送规则: 0-即时 1-延迟', type: 'integer', enum: [0, 1], example: 0, nullable: true)]
    #[ValidationRules(rules: 'in:0,1|nullable')]
    public ?int $push_rule = 0;

    #[OA\Property(property: 'minute', description: '延迟推送分钟数', type: 'integer', example: 0, nullable: true)]
    #[ValidationRules(rules: 'integer|nullable')]
    public ?int $minute = 0;
}
