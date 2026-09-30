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
namespace app\adminapi\schema\response\system;

use madong\swagger\schema\BaseResponseDTO;
use OpenApi\Attributes as OA;

#[OA\Schema(
    title: '管理员详情响应模型',
    description: '管理员详情接口的返回数据结构'
)]
class AdminResponse extends BaseResponseDTO
{
    #[OA\Property(
        property: 'id',
        description: '管理员ID',
        type: 'string',
        example: '1'
    )]
    public string $id;

    #[OA\Property(
        property: 'user_name',
        description: '用户名',
        type: 'string',
        example: 'admin'
    )]
    public string $user_name;

    #[OA\Property(
        property: 'real_name',
        description: '真实姓名',
        type: 'string',
        example: '管理员'
    )]
    public string $real_name;

    #[OA\Property(
        property: 'mobile_phone',
        description: '手机号码',
        type: 'string',
        example: '18888888888'
    )]
    public string $mobile_phone;

    #[OA\Property(
        property: 'email',
        description: '邮箱',
        type: 'string',
        example: 'admin@example.com'
    )]
    public string $email;

    #[OA\Property(
        property: 'status',
        description: '状态(0:禁用,1:启用)',
        type: 'integer',
        enum: [0, 1],
        example: 1,
    )]
    public int $status;

    #[OA\Property(
        property: 'dept_id',
        description: '部门ID',
        type: 'integer',
        example: 1
    )]
    public int $dept_id;

    #[OA\Property(
        property: 'avatar',
        description: '头像',
        type: 'string',
        example: '/upload/avatar.jpg'
    )]
    public string $avatar;

    #[OA\Property(
        property: 'is_super',
        description: '是否超级管理员(0:否,1:是)',
        type: 'integer',
        example: 1,
        enum: [0, 1]
    )]
    public int $is_super;

    #[OA\Property(
        property: 'is_tenant_admin',
        description: '是否租户管理员(0:否,1:是)',
        type: 'integer',
        example: 1,
        enum: [0, 1]
    )]
    public int $is_tenant_admin;

    #[OA\Property(
        property: 'created_at',
        description: '创建时间',
        type: 'string',
        format: 'date-time',
        example: '2025-10-10T16:28:51+08:00'
    )]
    public string $created_at;

    #[OA\Property(
        property: 'updated_at',
        description: '更新时间',
        type: 'string',
        format: 'date-time',
        example: '2025-10-10T16:28:51+08:00'
    )]
    public string $updated_at;
}
