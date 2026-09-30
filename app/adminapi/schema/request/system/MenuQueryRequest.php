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
namespace app\adminapi\schema\request\system;

use app\schema\request\BaseQueryRequest;
use OpenApi\Attributes as OA;
use WebmanTech\DTO\Attributes\ValidationRules;

#[OA\Schema(
    title: '菜单列表查询请求',
    description: '菜单列表接口的查询过滤参数'
)]
class MenuQueryRequest extends BaseQueryRequest
{
    #[OA\Property(
        property: 'type',
        description: '菜单类型（1菜单 2按钮 3接口）',
        type: 'integer',
        enum: [1, 2, 3],
        example: 1,
        nullable: true
    )]
    #[ValidationRules(rules: 'in:1,2,3|nullable')]
    public ?int $type = null;

    #[OA\Property(
        property: 'title',
        description: '菜单标题',
        type: 'string',
        example: '系统管理',
        nullable: true
    )]
    #[ValidationRules(rules: 'string|max:50|nullable')]
    public ?string $title = null;

    #[OA\Property(
        property: 'code',
        description: '权限标识',
        type: 'string',
        example: 'system:menu:list',
        nullable: true
    )]
    #[ValidationRules(rules: 'string|max:100|nullable')]
    public ?string $code = null;

    #[OA\Property(
        property: 'path',
        description: '路由地址',
        type: 'string',
        example: '/system/menu',
        nullable: true
    )]
    #[ValidationRules(rules: 'string|max:255|nullable')]
    public ?string $path = null;

    #[OA\Property(
        property: 'enabled',
        description: '状态（1启用 0禁用）',
        type: 'integer',
        enum: [0, 1],
        example: 1,
        nullable: true
    )]
    #[ValidationRules(rules: 'in:0,1|nullable')]
    public ?int $enabled = null;

}
