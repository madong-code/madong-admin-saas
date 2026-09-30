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
    title: '配置值响应模型',
    description: '配置值操作接口的返回数据结构'
)]
class ConfigValueResponse extends BaseResponseDTO
{
    #[OA\Property(
        property: 'code',
        description: '配置编码',
        type: 'string',
        example: 'site_name'
    )]
    public string $code;

    #[OA\Property(
        property: 'group_code',
        description: '分组编码',
        type: 'string',
        example: 'site'
    )]
    public string $group_code;

    #[OA\Property(
        property: 'name',
        description: '配置名称',
        type: 'string',
        example: '网站名称'
    )]
    public string $name;

    #[OA\Property(
        property: 'value',
        description: '配置值',
        type: 'mixed',
        example: '我的网站'
    )]
    public mixed $value;

    #[OA\Property(
        property: 'description',
        description: '配置描述',
        type: 'string',
        example: '网站名称配置项',
        nullable: true
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
        property: 'data_type',
        description: '数据类型（1:字符串 2:数字 3:布尔 4:数组 5:对象）',
        type: 'integer',
        enum: [1, 2, 3, 4, 5],
        example: 1
    )]
    public int $data_type;

    #[OA\Property(
        property: 'created_at',
        description: '创建时间',
        type: 'string',
        format: 'date-time',
        example: '2024-01-01 12:00:00'
    )]
    public string $created_at;

    #[OA\Property(
        property: 'updated_at',
        description: '更新时间',
        type: 'string',
        format: 'date-time',
        example: '2024-01-01 12:00:00'
    )]
    public string $updated_at;

    #[OA\Property(
        property: 'created_date',
        description: '创建时间（格式化）',
        type: 'string',
        example: '2024-01-01 12:00:00',
        nullable: true
    )]
    public ?string $created_date;

    #[OA\Property(
        property: 'updated_date',
        description: '更新时间（格式化）',
        type: 'string',
        example: '2024-01-01 12:00:00',
        nullable: true
    )]
    public ?string $updated_date;
}