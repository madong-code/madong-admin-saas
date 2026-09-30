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

namespace app\adminapi\schema\request\ops\crontab;

use app\schema\request\BaseQueryRequest;
use OpenApi\Attributes as OA;

#[OA\Schema(
    title: '定时任务日志查询请求',
    description: '定时任务日志列表查询过滤参数'
)]
class CrontabLogQueryRequest extends BaseQueryRequest
{
    #[OA\Property(
        property: 'id',
        description: '日志ID',
        type: 'string',
        example: '123456789012345678'
    )]
    public ?string $id = null;

    #[OA\Property(
        property: 'crontab_id',
        description: '定时任务ID',
        type: 'string',
        example: '987654321098765432'
    )]
    public ?string $crontab_id = null;

    #[OA\Property(
        property: 'target',
        description: '执行目标',
        type: 'string',
        example: 'app\command\Backup::run'
    )]
    public ?string $target = null;


    #[OA\Property(
        property: 'return_code',
        description: '返回码',
        type: 'integer',
        example: 0
    )]
    public ?int $return_code = null;

    #[OA\Property(
        property: 'running_time',
        description: '运行时间（秒）',
        type: 'integer',
        example: 10
    )]
    public ?int $running_time = null;

    #[OA\Property(
        property: 'created_at',
        description: '创建时间',
        type: 'string',
        format: 'date-time',
        example: '2024-05-12T10:30:00Z'
    )]
    public ?string $created_at = null;

}
