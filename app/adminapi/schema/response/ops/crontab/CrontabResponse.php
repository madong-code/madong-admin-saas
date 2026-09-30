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
class CrontabResponse extends BaseResponseDTO
{
    #[OA\Property(
        property: 'id',
        description: '任务ID',
        type: 'string',
        example: '123456789012345678'
    )]
    public string $id;

    #[OA\Property(
        property: 'name',
        description: '任务名称',
        type: 'string',
        example: '数据备份任务'
    )]
    public string $name;

    #[OA\Property(
        property: 'type',
        description: '任务类型(1:系统任务 2:自定义任务)',
        type: 'integer',
        enum: [1, 2],
        example: 1
    )]
    public int $type;

    #[OA\Property(
        property: 'command',
        description: '执行命令/类方法',
        type: 'string',
        example: 'app\command\Backup::run'
    )]
    public string $command;

    #[OA\Property(
        property: 'expression',
        description: 'CRON表达式',
        type: 'string',
        example: '0 0 * * *'
    )]
    public string $expression;

    #[OA\Property(
        property: 'status',
        description: '状态(1:运行中 0:已暂停)',
        type: 'integer',
        enum: [0, 1],
        example: 1
    )]
    public int $status;

    #[OA\Property(
        property: 'sort',
        description: '排序号',
        type: 'integer',
        example: 10
    )]
    public int $sort;

    #[OA\Property(
        property: 'tenant_id',
        description: '租户ID(雪花ID)；全局任务为null，租户任务有值。响应同时通过 with 关联返回 tenant 对象(含 id,name)',
        type: 'string',
        nullable: true,
        example: null
    )]
    public ?string $tenant_id = null;

    #[OA\Property(
        property: 'source',
        description: '任务来源: system-系统内置 / plugin:{插件名} 插件任务 / custom-自定义',
        type: 'string',
        example: 'custom'
    )]
    public string $source;

    #[OA\Property(
        property: 'parameter',
        description: '任务运行参数(key-value)，随任务模型一并传递给任务处理器(parse($crontab))',
        type: 'object',
        nullable: true,
        example: ['key' => 'value']
    )]
    public ?array $parameter = null;

    #[OA\Property(
        property: 'created_at',
        description: '创建时间',
        type: 'string',
        format: 'date-time',
        example: '2024-05-12T10:30:00Z'
    )]
    public string $created_at;
}