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

namespace app\schema\response;

use OpenApi\Attributes as OA;
use madong\swagger\schema\BaseResponseDTO;

#[OA\Schema(
    title: '分页数据响应',
    description: '分页数据响应结构'
)]
class PageResponse extends BaseResponseDTO
{
    #[OA\Property(
        property: 'total',
        description: '总记录数',
        type: 'integer',
        example: 100
    )]
    public int $total;

    #[OA\Property(
        property: 'page',
        description: '当前页码',
        type: 'integer',
        example: 1
    )]
    public int $page;

    #[OA\Property(
        property: 'limit',
        description: '每页条数',
        type: 'integer',
        example: 15
    )]
    public int $limit;

    #[OA\Property(
        property: 'items',
        description: '数据列表',
        type: 'array',
        items: new OA\Items(),
        example: []
    )]
    public array $items;

    public function __construct(
        int $total = 0,
        int $page = 1,
        int $limit = 15,
        array $items = []
    ) {
        $this->total = $total;
        $this->page = $page;
        $this->limit = $limit;
        $this->items = $items;
    }

    public static function make(int $total, int $page, int $limit, array $items): self
    {
        return new self($total, $page, $limit, $items);
    }

}
