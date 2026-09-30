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
namespace app\adminapi\schema\response\member;

use madong\swagger\schema\BaseResponseDTO;
use OpenApi\Attributes as OA;

/**
 * 会员Schema
 */
#[OA\Schema(
    title: '会员模型',
    description: '会员实体数据结构'
)]
class MemberResponse extends BaseResponseDTO
{
    #[OA\Property(
        property: 'id',
        description: '会员ID',
        type: 'integer',
        example: 1
    )]
    public int $id;

    #[OA\Property(
        property: 'username',
        description: '用户名',
        type: 'string',
        example: 'testuser'
    )]
    public string $username;

    #[OA\Property(
        property: 'email',
        description: '邮箱',
        type: 'string',
        example: 'test@example.com'
    )]
    public ?string $email = null;

    #[OA\Property(
        property: 'phone',
        description: '手机号',
        type: 'string',
        example: '13800138000'
    )]
    public ?string $phone = null;

    #[OA\Property(
        property: 'nickname',
        description: '昵称',
        type: 'string',
        example: '测试用户'
    )]
    public ?string $nickname = null;

    #[OA\Property(
        property: 'avatar',
        description: '头像',
        type: 'string',
        example: '/uploads/avatar.jpg'
    )]
    public ?string $avatar = null;

    #[OA\Property(
        property: 'level_id',
        description: '等级ID',
        type: 'integer',
        example: 1
    )]
    public int $level_id = 1;

    #[OA\Property(
        property: 'points',
        description: '积分',
        type: 'integer',
        example: 100
    )]
    public int $points = 0;

    #[OA\Property(
        property: 'balance',
        description: '余额',
        type: 'number',
        format: 'float',
        example: 50.00
    )]
    public float $balance = 0.0;

    #[OA\Property(
        property: 'gender',
        description: '性别：0-未知 1-男 2-女',
        type: 'integer',
        example: 1
    )]
    public int $gender = 0;

    #[OA\Property(
        property: 'gender_text',
        description: '性别文本',
        type: 'string',
        example: '男'
    )]
    public string $gender_text;

    #[OA\Property(
        property: 'birthday',
        description: '生日',
        type: 'string',
        example: '1990-01-01'
    )]
    public ?string $birthday = null;

    #[OA\Property(
        property: 'last_login_time',
        description: '最后登录时间',
        type: 'string',
        example: '2024-01-01 10:00:00'
    )]
    public ?string $last_login_time = null;

    #[OA\Property(
        property: 'last_login_ip',
        description: '最后登录IP',
        type: 'string',
        example: '127.0.0.1'
    )]
    public ?string $last_login_ip = null;

    #[OA\Property(
        property: 'login_count',
        description: '登录次数',
        type: 'integer',
        example: 10
    )]
    public int $login_count = 0;

    #[OA\Property(
        property: 'status',
        description: '状态：1-正常 0-禁用',
        type: 'integer',
        example: 1
    )]
    public int $status = 1;

    #[OA\Property(
        property: 'status_text',
        description: '状态文本',
        type: 'string',
        example: '正常'
    )]
    public string $status_text;

    #[OA\Property(
        property: 'created_at',
        description: '创建时间',
        type: 'string',
        example: '2024-01-01 10:00:00'
    )]
    public string $created_at;

    #[OA\Property(
        property: 'updated_at',
        description: '更新时间',
        type: 'string',
        example: '2024-01-01 10:00:00'
    )]
    public string $updated_at;
}