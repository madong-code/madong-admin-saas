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
namespace app\adminapi\schema\response\ops\logs;

use madong\swagger\schema\BaseResponseDTO;
use OpenApi\Attributes as OA;

#[OA\Schema(
    title: '登录日志详情响应模型',
    description: '登录日志详情接口的返回数据结构'
)]
class LoginLogResponse extends BaseResponseDTO
{
    #[OA\Property(
        property: 'id',
        description: '日志ID',
        type: 'string',
        example: '244861012925743104'
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
        property: 'ip',
        description: '登录IP',
        type: 'string',
        example: '127.0.0.1'
    )]
    public string $ip;

    #[OA\Property(
        property: 'ip_location',
        description: 'IP归属地',
        type: 'string',
        example: '未知'
    )]
    public ?string $ip_location;

    #[OA\Property(
        property: 'os',
        description: '操作系统',
        type: 'string',
        example: 'Windows'
    )]
    public ?string $os;

    #[OA\Property(
        property: 'browser',
        description: '浏览器',
        type: 'string',
        example: 'Chrome'
    )]
    public ?string $browser;

    #[OA\Property(
        property: 'status',
        description: '登录状态（1成功 0失败）',
        type: 'integer',
        enum: [0, 1],
        example: 1
    )]
    public int $status;

    #[OA\Property(
        property: 'message',
        description: '登录提示信息',
        type: 'string',
        example: '',
        nullable: true
    )]
    public ?string $message;

    #[OA\Property(
        property: 'login_time',
        description: '登录时间戳',
        type: 'integer',
        example: 1762417814
    )]
    public int $login_time;

    #[OA\Property(
        property: 'key',
        description: '登录会话密钥',
        type: 'string',
        example: '0c7792fa9477cd736f1cf2c27bbfff61'
    )]
    public string $key;

    #[OA\Property(
        property: 'expires_at',
        description: '会话过期时间戳',
        type: 'integer',
        example: 1762425014
    )]
    public int $expires_at;

    #[OA\Property(
        property: 'created_at',
        description: '创建时间（UTC）',
        type: 'string',
        format: 'date-time',
        example: '2025-11-06T08:30:14.000000Z'
    )]
    public string $created_at;

    #[OA\Property(
        property: 'updated_at',
        description: '更新时间（UTC）',
        type: 'string',
        format: 'date-time',
        example: '2025-11-06T08:30:14.000000Z'
    )]
    public string $updated_at;  // 新增字段

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
        property: 'remark',
        description: '备注',
        type: 'string',
        example: null,
        nullable: true
    )]
    public ?string $remark;

    #[OA\Property(
        property: 'created_date',
        description: '创建时间（本地）',
        type: 'string',
        example: '2025-11-06 16:30:14'
    )]
    public string $created_date;

    #[OA\Property(
        property: 'updated_date',
        description: '更新时间（本地）',
        type: 'string',
        example: '2025-11-06 16:30:14'
    )]
    public string $updated_date;
}
