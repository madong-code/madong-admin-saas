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
    title: '定时任务列表查询请求',
    description: '定时任务列表查询过滤参数'
)]
class CrontabQueryRequest extends BaseQueryRequest
{


    #[OA\Property(
        property: 'type',
        description: '任务类型',
        type: 'integer',
        example: 1
    )]
    public ?int $type = null;

    #[OA\Property(
        property: 'task_cycle',
        description: '任务周期',
        type: 'integer',
        example: 1
    )]
    public ?int $task_cycle = null;

    #[OA\Property(
        property: 'singleton',
        description: '是否单例执行',
        type: 'integer',
        enum: [0, 1],
        example: 1
    )]
    public ?int $singleton = null;

    #[OA\Property(
        property: 'enabled',
        description: '是否启用',
        type: 'integer',
        enum: [0, 1],
        example: 0
    )]
    public ?int $enabled = null;

    #[OA\Property(
        property: 'title',
        description: '任务标题',
        type: 'string',
        example: '数据备份任务'
    )]
    public ?string $title = null;

    #[OA\Property(
        property: 'target',
        description: '执行目标',
        type: 'string',
        example: 'app\command\Backup::run'
    )]
    public ?string $target = null;
}
