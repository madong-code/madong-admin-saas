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
    title: '字典项详情响应模型',
    description: '字典项详情接口的返回数据结构'
)]
class DictItemResponse extends BaseResponseDTO
{
    #[OA\Property(
        property: 'id',
        description: '字典项ID',
        type: 'string',  // 调整为字符串类型（匹配JSON中的长ID）
        example: '242218273109180416'
    )]
    public string $id;

    #[OA\Property(
        property: 'dict_id',
        description: '字典ID（关联主表）',
        type: 'string',  // 调整为字符串类型
        example: '242218273104986112'
    )]
    public string $dict_id;

    #[OA\Property(
        property: 'label',
        description: '显示标签',
        type: 'string',
        example: '默认分组'
    )]
    public string $label;

    #[OA\Property(
        property: 'value',
        description: '选项值',
        type: 'string',
        example: 'value1'
    )]
    public string $value;

    #[OA\Property(
        property: 'code',
        description: '字典项编码',
        type: 'string',
        example: 'default'
    )]
    public string $code;  // 新增字段

    #[OA\Property(
        property: 'color',
        description: '颜色（如#4CAF50）',
        type: 'string',
        example: null,
        nullable: true
    )]
    public ?string $color;

    #[OA\Property(
        property: 'other_class',
        description: '额外样式类',
        type: 'string',
        example: null,
        nullable: true
    )]
    public ?string $other_class;  // 新增字段

    #[OA\Property(
        property: 'sort',
        description: '排序号',
        type: 'integer',
        example: 1
    )]
    public int $sort;

    #[OA\Property(
        property: 'enabled',
        description: '状态（1启用 0禁用）',
        type: 'integer',
        enum: [0, 1],
        example: 1  // 原status字段重命名为enabled
    )]
    public int $enabled;

    #[OA\Property(
        property: 'created_by',
        description: '创建人ID',
        type: 'integer',
        example: 1
    )]
    public int $created_by;  // 新增字段

    #[OA\Property(
        property: 'updated_by',
        description: '更新人ID',
        type: 'integer',
        example: 1
    )]
    public int $updated_by;  // 新增字段

    #[OA\Property(
        property: 'remark',
        description: '备注',
        type: 'string',
        example: '',
        nullable: true
    )]
    public ?string $remark;  // 新增字段

    #[OA\Property(
        property: 'created_at',
        description: '创建时间',
        type: 'string',
        format: 'date-time',
        example: null,
        nullable: true
    )]
    public ?string $created_at;  // 调整为可空

    #[OA\Property(
        property: 'updated_at',
        description: '更新时间',
        type: 'string',
        format: 'date-time',
        example: null,
        nullable: true
    )]
    public ?string $updated_at;  // 新增字段

    #[OA\Property(
        property: 'deleted_at',
        description: '删除时间',
        type: 'string',
        format: 'date-time',
        example: null,
        nullable: true
    )]
    public ?string $deleted_at;  // 新增字段

    #[OA\Property(
        property: 'created_date',
        description: '创建日期（格式化）',
        type: 'string',
        example: null,
        nullable: true
    )]
    public ?string $created_date;  // 新增字段

    #[OA\Property(
        property: 'updated_date',
        description: '更新日期（格式化）',
        type: 'string',
        example: null,
        nullable: true
    )]
    public ?string $updated_date;  // 新增字段
}
