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
    title: '字典详情响应模型',
    description: '字典详情接口的返回数据结构'
)]
class DictResponse extends BaseResponseDTO
{
    #[OA\Property(
        property: 'id',
        description: '字典ID',
        type: 'string',  // 调整为字符串类型（匹配JSON中的长ID）
        example: '242218273104986112'
    )]
    public string $id;

    #[OA\Property(
        property: 'group_code',
        description: '字典分组编码',
        type: 'string',
        example: 'default'
    )]
    public string $group_code;

    #[OA\Property(
        property: 'name',
        description: '字典名称',
        type: 'string',
        example: '所属分组'  // 原 dict_name 重命名为 name
    )]
    public string $name;

    #[OA\Property(
        property: 'code',
        description: '字典编码（唯一）',
        type: 'string',
        example: 'sys_dict_group_code'  // 原 dict_type 重命名为 code
    )]
    public string $code;

    #[OA\Property(
        property: 'sort',
        description: '排序号',
        type: 'integer',
        example: 1
    )]
    public int $sort;

    #[OA\Property(
        property: 'data_type',
        description: '数据类型（1:字符串 2:数字 3:布尔）',
        type: 'integer',
        enum: [1, 2, 3],
        example: 1
    )]
    public int $data_type;

    #[OA\Property(
        property: 'description',
        description: '字典描述',
        type: 'string',
        example: '',
        nullable: true  // 原 remark 重命名为 description
    )]
    public ?string $description;

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
        type: 'string',
        example: 1
    )]
    public int|string $created_by;

    #[OA\Property(
        property: 'updated_by',
        description: '更新人ID',
        type: 'string',
        example: 1
    )]
    public int|string $updated_by;

    #[OA\Property(
        property: 'created_at',
        description: '创建时间（原始）',
        type: 'string',
        format: 'date-time',
        example: null,
        nullable: true
    )]
    public ?string $created_at;

    #[OA\Property(
        property: 'updated_at',
        description: '更新时间（原始）',
        type: 'string',
        format: 'date-time',
        example: null,
        nullable: true
    )]
    public ?string $updated_at;

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
        property: 'created_date',
        description: '创建时间（格式化）',
        type: 'string',
        example: null,
        nullable: true
    )]
    public ?string $created_date;

    #[OA\Property(
        property: 'updated_date',
        description: '更新时间（格式化）',
        type: 'string',
        example: null,
        nullable: true
    )]
    public ?string $updated_date;
}
