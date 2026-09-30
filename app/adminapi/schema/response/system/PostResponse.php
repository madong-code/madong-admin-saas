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
    title: '岗位详情响应模型',
    description: '岗位详情接口的返回数据结构'
)]
class PostResponse extends BaseResponseDTO
{
    #[OA\Property(
        property: 'id',
        description: '岗位ID',
        type: 'string',
        example: '247048545520582656'
    )]
    public string $id;

    #[OA\Property(
        property: 'dept_id',
        description: '部门ID',
        type: 'string',
        example: '246996721795072000'
    )]
    public string $dept_id;

    #[OA\Property(
        property: 'code',
        description: '岗位编码',
        type: 'string',
        example: 'SDF'
    )]
    public string $code;

    #[OA\Property(
        property: 'name',
        description: '岗位名称',
        type: 'string',
        example: 'AD'
    )]
    public string $name;

    #[OA\Property(
        property: 'sort',
        description: '排序号',
        type: 'integer',
        example: 0
    )]
    public int $sort;

    #[OA\Property(
        property: 'enabled',
        description: '状态（1启用 0禁用）',
        type: 'integer',
        enum: [0, 1],
        example: 1
    )]
    public int $enabled;

    #[OA\Property(
        property: 'created_by',
        description: '创建人ID',
        type: 'integer',
        example: 1
    )]
    public int $created_by;

    #[OA\Property(
        property: 'updated_by',
        description: '更新人ID',
        type: 'integer',
        example: null,
        nullable: true
    )]
    public ?int $updated_by;

    #[OA\Property(
        property: 'created_at',
        description: '创建时间',
        type: 'string',
        format: 'date-time',
        example: '2025-11-12T09:22:43.000000Z'
    )]
    public string $created_at;

    #[OA\Property(
        property: 'updated_at',
        description: '更新时间',
        type: 'string',
        format: 'date-time',
        example: '2025-11-12T09:22:43.000000Z'
    )]
    public string $updated_at;

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
        example: 'ASDF',
        nullable: true
    )]
    public ?string $remark;

    #[OA\Property(
        property: 'created_date',
        description: '创建日期（本地格式化）',
        type: 'string',
        example: '2025-11-12 17:22:43'
    )]
    public string $created_date;

    #[OA\Property(
        property: 'updated_date',
        description: '更新日期（本地格式化）',
        type: 'string',
        example: '2025-11-12 17:22:43'
    )]
    public string $updated_date;
}
