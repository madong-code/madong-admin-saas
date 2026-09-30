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
use WebmanTech\DTO\Attributes\ValidationRules;

#[OA\Schema(
    title: '数据回收站列表查询请求',
    description: '数据回收站列表接口的查询过滤参数'
)]
class RecycleBinQueryRequest extends BaseQueryRequest
{


    #[OA\Property(
        property: 'table_name',
        description: '数据表名',
        type: 'string',
        example: 'sys_user'
    )]
    #[ValidationRules('trim|max:50')]
    public ?string $table_name = null;

    #[OA\Property(
        property: 'original_id',
        description: '原始数据ID',
        type: 'string',
        example: '232593675996626944'
    )]
    #[ValidationRules('trim|max:32')]
    public ?string $original_id = null;

    #[OA\Property(
        property: 'enabled',
        description: '是否已还原(0=未还原,1=已还原)',
        type: 'integer',
        enum: [0, 1],
        example: 0
    )]
    #[ValidationRules('in:0,1')]
    public ?int $enabled = null;

    #[OA\Property(
        property: 'start_time',
        description: '创建开始时间',
        type: 'string',
        format: 'date-time',
        example: '2025-10-01T00:00:00Z'
    )]
    #[ValidationRules('dateFormat:Y-m-d H:i:s')]
    public ?string $start_time = null;

    #[OA\Property(
        property: 'end_time',
        description: '创建结束时间',
        type: 'string',
        format: 'date-time',
        example: '2025-10-31T23:59:59Z'
    )]
    #[ValidationRules('dateFormat:Y-m-d H:i:s|after:start_time')]
    public ?string $end_time = null;

}
