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
    title: '回收站记录详情响应模型',
    description: '回收站记录详情接口的返回数据结构'
)]
class RecycleBinResponse extends BaseResponseDTO
{
    #[OA\Property(
        property: 'id',
        description: 'ID',
        type: 'string',
        example: '123456789012345678'
    )]
    public string $id;

    #[OA\Property(
        property: 'original_id',
        description: '原始数据ID',
        type: 'string',
        example: '232593675996626944'
    )]
    public string $original_id;

    #[OA\Property(
        property: 'data',
        description: '回收数据',
        type: 'object',
        example: ['id' => 100, 'name' => '测试数据']
    )]
    public array $data;

    #[OA\Property(
        property: 'table_name',
        description: '数据表名',
        type: 'string',
        example: 'sys_upload'
    )]
    public string $table_name;

    #[OA\Property(
        property: 'table_prefix',
        description: '数据表前缀',
        type: 'string',
        example: 'ma_'
    )]
    public string $table_prefix;

    #[OA\Property(
        property: 'enabled',
        description: '是否已还原(0=未还原,1=已还原)',
        type: 'integer',
        enum: [0, 1],
        example: 0
    )]
    public int $enabled;

    #[OA\Property(
        property: 'ip',
        description: '操作IP',
        type: 'string',
        example: '223.152.76.8'
    )]
    public string $ip;

    #[OA\Property(
        property: 'operate_by',
        description: '操作人ID',
        type: 'integer',
        example: 0
    )]
    public int $operate_by;

    #[OA\Property(
        property: 'created_at',
        description: '创建时间',
        type: 'string',
        format: 'date-time',
        example: '2025-10-03T12:14:38.000000Z'
    )]
    public string $created_at;

    #[OA\Property(
        property: 'updated_at',
        description: '更新时间',
        type: 'string',
        format: 'date-time',
        example: '2025-10-03T12:14:38.000000Z'
    )]
    public string $updated_at;

    #[OA\Property(
        property: 'created_date',
        description: '创建日期(格式化)',
        type: 'string',
        example: '2025-10-03 20:14:38'
    )]
    public string $created_date;

    #[OA\Property(
        property: 'updated_date',
        description: '更新日期(格式化)',
        type: 'string',
        example: '2025-10-03 20:14:38'
    )]
    public string $updated_date;

    #[OA\Property(
        property: 'operate_name',
        description: '操作人名称',
        type: 'string',
        example: null,
        nullable: true
    )]
    public ?string $operate_name;

    #[OA\Property(
        property: 'operate',
        description: '操作类型',
        type: 'string',
        example: null,
        nullable: true
    )]
    public ?string $operate;
}
