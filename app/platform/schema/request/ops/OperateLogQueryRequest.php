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
namespace app\platform\schema\request\ops;

use app\schema\request\BaseQueryRequest;
use OpenApi\Attributes as OA;
use WebmanTech\DTO\Attributes\ValidationRules;

#[OA\Schema(
    title: '操作日志列表查询请求',
    description: '操作日志列表接口的查询过滤参数'
)]
class OperateLogQueryRequest extends BaseQueryRequest
{

    #[OA\Property(
        property: 'user_name',
        description: '操作用户',
        type: 'string',
        example: 'admin',
        nullable: true
    )]
    #[ValidationRules(rules: 'string|max:50|nullable')]
    public ?string $user_name = null;

    #[OA\Property(
        property: 'name',
        description: '操作名称',
        type: 'string',
        example: '数据字典',
        nullable: true
    )]
    #[ValidationRules(rules: 'string|max:100|nullable')]
    public ?string $name = null;

    #[OA\Property(
        property: 'method',
        description: '请求方法',
        type: 'string',
        enum: ['GET', 'POST', 'PUT', 'DELETE', 'PATCH'],
        example: 'GET',
        nullable: true
    )]
    #[ValidationRules(rules: 'string|in:GET,POST,PUT,DELETE,PATCH|nullable')]
    public ?string $method = null;

    #[OA\Property(
        property: 'ip',
        description: '操作IP',
        type: 'string',
        example: '220.165.172.45',
        nullable: true
    )]
    #[ValidationRules(rules: 'string|ip|nullable')]
    public ?string $ip = null;

    #[OA\Property(
        property: 'start_date',
        description: '操作开始日期',
        type: 'string',
        format: 'date',
        example: '2025-10-01',
        nullable: true
    )]
    #[ValidationRules(rules: 'date|nullable')]
    public ?string $start_date = null;

    #[OA\Property(
        property: 'end_date',
        description: '操作结束日期',
        type: 'string',
        format: 'date',
        example: '2025-10-31',
        nullable: true
    )]
    #[ValidationRules(rules: 'date|after_or_equal:start_date|nullable')]
    public ?string $end_date = null;
}
