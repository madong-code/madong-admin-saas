<?php
declare(strict_types=1);

/**
 *+------------------
 * madong - 消息管理响应 DTO
 *+------------------
 * Copyright (c) https://gitee.com/motion-code  All rights reserved.
 *+------------------
 * Author: Mr. April (405784684@qq.com)
 *+------------------
 * Official Website: https://madong.tech
 */

namespace app\adminapi\schema\response\content\message\manage;

use OpenApi\Attributes as OA;

#[OA\Schema(
    title: '消息管理响应模型',
    description: '消息管理接口的返回数据结构（含模板信息）'
)]
class ManageResponse
{
    #[OA\Property(property: 'id', description: '消息ID（雪花ID）', type: 'string', example: '245329792713883648')]
    public string $id;

    #[OA\Property(property: 'category_id', description: '所属分类ID', type: 'string', example: '123456789012345678')]
    public string $category_id;

    #[OA\Property(property: 'category_name', description: '分类名称', type: 'string', example: '订单通知')]
    public string $category_name;

    #[OA\Property(property: 'key', description: '消息标识', type: 'string', example: 'order_paid')]
    public string $key;

    #[OA\Property(property: 'name', description: '消息名称', type: 'string', example: '订单支付通知')]
    public string $name;

    #[OA\Property(property: 'description', description: '描述', type: 'string', example: '订单支付成功后发送通知', nullable: true)]
    public ?string $description;

    #[OA\Property(property: 'default_on', description: '默认是否开启订阅', type: 'integer', enum: [0, 1], example: 1)]
    public int $default_on;

    #[OA\Property(property: 'nav_type', description: '导航类型', type: 'string', enum: ['router', 'url', 'none'], example: 'router', nullable: true)]
    public ?string $nav_type;

    #[OA\Property(property: 'nav_value', description: '导航值', type: 'string', example: '/order/detail', nullable: true)]
    public ?string $nav_value;

    #[OA\Property(property: 'sort', description: '排序', type: 'integer', example: 0)]
    public int $sort;

    #[OA\Property(property: 'is_system', description: '是否系统内置', type: 'integer', enum: [0, 1], example: 1)]
    public int $is_system;

    #[OA\Property(property: 'enabled', description: '是否启用', type: 'integer', enum: [0, 1], example: 1)]
    public int $enabled;

    #[OA\Property(property: 'template', description: '关联模板信息', type: 'object', nullable: true)]
    public ?array $template;

    #[OA\Property(property: 'created_at', description: '创建时间戳', type: 'integer', example: 1700000000)]
    public int $created_at;

    #[OA\Property(property: 'updated_at', description: '更新时间戳', type: 'integer', example: 1700000000)]
    public int $updated_at;
}
