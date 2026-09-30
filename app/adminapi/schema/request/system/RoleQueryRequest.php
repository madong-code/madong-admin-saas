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

#[OA\Schema(
    title: '角色列表查询请求',
    description: '角色列表接口的查询过滤参数'
)]
class RoleQueryRequest extends BaseQueryRequest
{


    #[OA\Property(
        property: 'code',
        description: '角色编码',
        type: 'string',
        example: 'ADMIN'
    )]
    public ?string $code = null;

    #[OA\Property(
        property: 'name',
        description: '角色名称',
        type: 'string',
        example: '管理员'
    )]
    public ?string $name = null;

    #[OA\Property(
        property: 'enabled',
        description: '角色状态(1:启用 0:禁用)',
        type: 'integer',
        enum: [0, 1],
        example: 1
    )]
    public ?int $enabled = null;

}
