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
namespace app\adminapi\schema\response\content\notice;

use OpenApi\Attributes as OA;

#[OA\Schema(
    title: '系统公告详情响应模型',
    description: '系统公告详情接口的返回数据结构'
)]
class NoticeResponse
{
    #[OA\Property(
        property: 'id',
        description: '公告ID',
        type: 'string',
        example: '234161405304516608'
    )]
    public string $id;

    #[OA\Property(
        property: 'type',
        description: '公告类型',
        type: 'string',
        enum: ['notice', 'announcement', 'alert'],
        example: 'notice'
    )]
    public string $type;

    #[OA\Property(
        property: 'title',
        description: '公告标题',
        type: 'string',
        example: '111111111111111'
    )]
    public string $title;

    #[OA\Property(
        property: 'content',
        description: '公告内容',
        type: 'string',
        example: '222222222222222222222'
    )]
    public string $content;

    #[OA\Property(
        property: 'sort',
        description: '排序号',
        type: 'integer',
        example: 10
    )]
    public int $sort;

    #[OA\Property(
        property: 'enabled',
        description: '是否启用（1启用 0禁用）',
        type: 'integer',
        enum: [0, 1],
        example: 0
    )]
    public int $enabled;

    #[OA\Property(
        property: 'uuid',
        description: '唯一标识',
        type: 'string',
        example: 'a6a0ff4f-81dc-c033-083e-99611865251c'
    )]
    public string $uuid;

    #[OA\Property(
        property: 'created_dept',
        description: '创建部门',
        type: 'string',
        example: null,
        nullable: true
    )]
    public ?string $created_dept;

    #[OA\Property(
        property: 'created_by',
        description: '创建人ID',
        type: 'integer',
        example: 2
    )]
    public int $created_by;

    #[OA\Property(
        property: 'created_at',
        description: '创建时间',
        type: 'string',
        format: 'date-time',
        example: '2025-10-07T19:53:49.000000Z'
    )]
    public string $created_at;

    #[OA\Property(
        property: 'updated_by',
        description: '更新人ID',
        type: 'integer',
        example: 2
    )]
    public int $updated_by;

    #[OA\Property(
        property: 'updated_at',
        description: '更新时间',
        type: 'string',
        format: 'date-time',
        example: '2025-10-21T13:43:42.000000Z'
    )]
    public string $updated_at;

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
        description: '创建日期（本地格式化）',
        type: 'string',
        example: '2025-10-08 03:53:49'
    )]
    public string $created_date;

    #[OA\Property(
        property: 'updated_date',
        description: '更新日期（本地格式化）',
        type: 'string',
        example: '2025-10-21 21:43:42'
    )]
    public string $updated_date;
}
