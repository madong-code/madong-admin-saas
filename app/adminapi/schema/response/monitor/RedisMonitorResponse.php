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
    title: 'Redis监控信息响应模型',
    description: 'Redis服务器监控接口的返回数据结构'
)]
class RedisMonitorResponse extends BaseResponseDTO
{
    #[OA\Property(
        property: 'redis_version',
        description: 'Redis版本',
        type: 'string',
        example: '6.2.6'
    )]
    public string $redis_version;

    #[OA\Property(
        property: 'used_memory',
        description: '已使用内存(字节)',
        type: 'integer',
        example: 1048576
    )]
    public int $used_memory;

    #[OA\Property(
        property: 'used_memory_human',
        description: '人类可读已使用内存',
        type: 'string',
        example: '1.00M'
    )]
    public string $used_memory_human;

    #[OA\Property(
        property: 'connected_clients',
        description: '已连接客户端数',
        type: 'integer',
        example: 10
    )]
    public int $connected_clients;

    #[OA\Property(
        property: 'uptime_in_days',
        description: '运行天数',
        type: 'integer',
        example: 30
    )]
    public int $uptime_in_days;

    #[OA\Property(
        property: 'keyspace_hits',
        description: '键空间命中次数',
        type: 'integer',
        example: 10000
    )]
    public int $keyspace_hits;

    #[OA\Property(
        property: 'keyspace_misses',
        description: '键空间未命中次数',
        type: 'integer',
        example: 100
    )]
    public int $keyspace_misses;
}
