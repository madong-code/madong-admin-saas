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

use app\schema\request\BaseFormRequest;
use OpenApi\Attributes as OA;

#[OA\Schema(
    title: '定时任务表单',
    description: '定时任务创建和编辑接口共用的表单请求参数'
)]
class CrontabFormRequest extends BaseFormRequest
{
    #[OA\Property(
        property: 'type',
        description: '任务类型',
        type: 'integer',
        example: 1
    )]
    public int $type;

    #[OA\Property(
        property: 'task_cycle',
        description: '任务周期',
        type: 'integer',
        example: 1
    )]
    public int $task_cycle;

    #[OA\Property(
        property: 'singleton',
        description: '是否单例执行',
        type: 'integer',
        enum: [0, 1],
        example: 1
    )]
    public int $singleton;

    #[OA\Property(
        property: 'enabled',
        description: '是否启用',
        type: 'integer',
        enum: [0, 1],
        example: 0
    )]
    public int $enabled;

    #[OA\Property(
        property: 'title',
        description: '任务标题',
        type: 'string',
        example: '数据备份任务'
    )]
    public string $title;

    #[OA\Property(
        property: 'hour',
        description: '小时',
        type: 'integer',
        example: 1
    )]
    public int $hour;

    #[OA\Property(
        property: 'minute',
        description: '分钟',
        type: 'integer',
        example: 1
    )]
    public int $minute;

    #[OA\Property(
        property: 'target',
        description: '执行目标',
        type: 'string',
        example: 'app\command\Backup::run'
    )]
    public string $target;
}
