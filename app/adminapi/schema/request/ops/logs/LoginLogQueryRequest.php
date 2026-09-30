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
namespace app\adminapi\schema\request\ops\logs;

use app\schema\request\BaseQueryRequest;
use OpenApi\Attributes as OA;
use WebmanTech\DTO\Attributes\ValidationRules;

#[OA\Schema(
    title: '登录日志列表查询请求',
    description: '登录日志列表接口的查询过滤参数'
)]
class LoginLogQueryRequest extends BaseQueryRequest
{
    #[OA\Property(
        description: '用户名',
        type: 'string',
        example: 'admin',
        nullable: true
    )]
    #[ValidationRules(rules: 'string|max:50|nullable')]
    public ?string $username = null;

    #[OA\Property(
        description: '登录IP',
        type: 'string',
        example: '192.168.1.1',
        nullable: true
    )]
    #[ValidationRules(rules: 'string|max:50|nullable')]
    public ?string $ipaddr = null;

    #[OA\Property(
        description: '登录状态(0失败 1成功)',
        type: 'integer',
        enum: [0, 1],
        example: 1,
        nullable: true
    )]
    #[ValidationRules(rules: 'in:0,1|nullable')]
    public ?int $status = null;

    #[OA\Property(
        description: '开始时间',
        type: 'string',
        format: 'date-time',
        example: '2025-01-01 00:00:00',
        nullable: true
    )]
    #[ValidationRules(rules: 'date_format:Y-m-d H:i:s|nullable')]
    public ?string $start_time = null;

    #[OA\Property(
        description: '结束时间',
        type: 'string',
        format: 'date-time',
        example: '2025-01-31 23:59:59',
        nullable: true
    )]
    #[ValidationRules(rules: 'date_format:Y-m-d H:i:s|nullable|after:start_time')]
    public ?string $end_time = null;

}
