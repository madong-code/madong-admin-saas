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

namespace app\adminapi\schema\response\monitor;


use madong\swagger\schema\BaseResponseDTO;
use OpenApi\Attributes as OA;

#[OA\Schema(
    title: '服务器监控信息响应模型',
    description: '服务器CPU、内存、磁盘及PHP环境监控接口的返回数据结构'
)]
class ServerMonitorResponse extends BaseResponseDTO
{
    #[OA\Property(
        property: 'cpu',
        description: 'CPU监控信息',
        properties: [
            new OA\Property(property: 'usage', description: 'CPU使用率(%)', type: 'number', format: 'float', example: 25.5),
            new OA\Property(property: 'cores', description: 'CPU核心数', type: 'integer', example: 8),
            new OA\Property(property: 'model', description: 'CPU型号', type: 'string', example: 'Intel(R) Core(TM) i7-8700K'),
        ],
        type: 'object',
    )]
    public object $cpu;

    #[OA\Property(
        property: 'memory',
        description: '内存监控信息',
        properties: [
            new OA\Property(property: 'total', description: '总内存(MB)', type: 'integer', example: 16384),
            new OA\Property(property: 'used', description: '已使用内存(MB)', type: 'integer', example: 8192),
            new OA\Property(property: 'free', description: '空闲内存(MB)', type: 'integer', example: 8192),
            new OA\Property(property: 'usage', description: '内存使用率(%)', type: 'number', format: 'float', example: 50.0),
        ],
        type: 'object',
    )]
    public object $memory;

    #[OA\Property(
        property: 'disk',
        description: '磁盘监控信息',
        properties: [
            new OA\Property(property: 'total', description: '总磁盘空间(GB)', type: 'integer', example: 512),
            new OA\Property(property: 'used', description: '已使用磁盘空间(GB)', type: 'integer', example: 256),
            new OA\Property(property: 'free', description: '空闲磁盘空间(GB)', type: 'integer', example: 256),
            new OA\Property(property: 'usage', description: '磁盘使用率(%)', type: 'number', format: 'float', example: 50.0),
        ],
        type: 'object',
    )]
    public object $disk;

    #[OA\Property(
        property: 'php',
        description: 'PHP环境信息',
        properties: [
            new OA\Property(property: 'version', description: 'PHP版本', type: 'string', example: '7.4.33'),
            new OA\Property(property: 'max_execution_time', description: '最大执行时间(秒)', type: 'integer', example: 30),
            new OA\Property(property: 'memory_limit', description: '内存限制', type: 'string', example: '128M'),
        ],
        type: 'object',
    )]
    public object $php;
}
