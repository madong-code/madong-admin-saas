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

namespace app\adminapi\schema\response\ops\crontab;

use madong\swagger\schema\BaseResponseDTO;
use OpenApi\Attributes as OA;

#[OA\Schema(
    title: '定时任务信息',
    description: '系统定时任务数据结构'
)]
class CrontabLogResponse extends BaseResponseDTO
{
    #[OA\Property(
        property: 'id',
        description: '日志ID',
        type: 'string',
        example: '123456789012345678'
    )]
    public string $id;

    #[OA\Property(
        property: 'crontab_id',
        description: '定时任务ID',
        type: 'integer',
        example: 1001
    )]
    public int $crontab_id;

    #[OA\Property(
        property: 'task_name',
        description: '任务名称',
        type: 'string',
        example: '数据备份任务'
    )]
    public string $task_name;

    #[OA\Property(
        property: 'exec_time',
        description: '执行时间',
        type: 'string',
        format: 'date-time',
        example: '2023-11-15 08:30:00'
    )]
    public string $exec_time;

    #[OA\Property(
        property: 'status',
        description: '执行状态：0-失败 1-成功',
        type: 'integer',
        enum: [0, 1],
        example: 1
    )]
    public int $status;

    #[OA\Property(
        property: 'output',
        description: '执行输出日志',
        type: 'string',
        example: '备份成功，文件大小：2048KB'
    )]
    public string $output;

    #[OA\Property(
        property: 'create_time',
        description: '创建时间',
        type: 'string',
        format: 'date-time',
        example: '2023-11-15 08:30:01'
    )]
    public string $create_time;

    #[OA\Property(
        property: 'update_time',
        description: '更新时间',
        type: 'string',
        format: 'date-time',
        example: '2023-11-15 08:30:01'
    )]
    public string $update_time;
}