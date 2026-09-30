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

namespace app\schema\request;

use OpenApi\Attributes as OA;
use madong\swagger\schema\BaseRequestDTO;

#[OA\Schema(
    title: '基础分页查询请求',
    description: '通用分页查询请求参数'
)]
class BaseQueryRequest extends BaseRequestDTO
{
    #[OA\Property(
        property: 'page',
        description: '当前页码',
        type: 'integer|null',
        minimum: 0,
        example: 1
    )]
    public ?int $page = 1;

    #[OA\Property(
        property: 'limit',
        description: '每页条数',
        type: 'integer',
        maximum: 999999,
        minimum: 1,
        example: 15
    )]
    public ?int $limit = 15;

    #[OA\Property(
        property: 'sort_field',
        description: '排序字段',
        type: 'string',
        example: 'id'
    )]
    public ?string $sortField = null;

    #[OA\Property(
        property: 'sort_order',
        description: '排序方式',
        type: 'string',
        enum: ['asc', 'desc'],
        example: 'desc'
    )]
    public ?string $sortOrder = 'desc';

    #[OA\Property(
        property: 'keyword',
        description: '关键词搜索',
        type: 'string',
        example: ''
    )]
    public ?string $keyword = null;

}
