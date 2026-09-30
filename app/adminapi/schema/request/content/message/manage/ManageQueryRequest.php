<?php
declare(strict_types=1);

/**
 *+------------------
 * madong - 消息管理查询请求 DTO
 *+------------------
 * Copyright (c) https://gitee.com/motion-code  All rights reserved.
 *+------------------
 * Author: Mr. April (405784684@qq.com)
 *+------------------
 * Official Website: https://madong.tech
 */

namespace app\adminapi\schema\request\content\message\manage;

use app\schema\request\BaseQueryRequest;
use OpenApi\Attributes as OA;
use WebmanTech\DTO\Attributes\ValidationRules;

#[OA\Schema(
    title: '消息管理列表查询请求',
    description: '消息管理接口的查询过滤参数'
)]
class ManageQueryRequest extends BaseQueryRequest
{
    #[OA\Property(property: 'name', description: '消息名称', type: 'string', example: '通知', nullable: true)]
    #[ValidationRules(rules: 'string|max:100|nullable')]
    public ?string $name = null;

    #[OA\Property(property: 'category_id', description: '所属分类ID', type: 'string', example: '123456789012345678', nullable: true)]
    #[ValidationRules(rules: 'string|nullable')]
    public ?string $category_id = null;

    #[OA\Property(property: 'enabled', description: '启用状态', type: 'integer', enum: [0, 1], example: 1, nullable: true)]
    #[ValidationRules(rules: 'in:0,1|nullable')]
    public ?int $enabled = null;

    #[OA\Property(property: 'type', description: '模板类型', type: 'string', enum: ['system', 'sms', 'email', 'webhook'], example: 'system', nullable: true)]
    #[ValidationRules(rules: 'string|max:30|nullable')]
    public ?string $type = null;
}
