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

namespace app\schema\request\traits;

use OpenApi\Attributes as OA;

trait WithSorting
{
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
        property: 'order',
        description: '排序规则 (例: sort asc, id desc)',
        type: 'string',
        example: 'id desc'
    )]
    public ?string $order = null;

    public function getSortField(): ?string
    {
        return $this->sortField;
    }

    public function getSortOrder(): string
    {
        return in_array(strtolower($this->sortOrder ?? ''), ['asc', 'desc'])
            ? strtolower($this->sortOrder)
            : 'desc';
    }

    public function getOrder(): ?string
    {
        return $this->order;
    }

    /**
     * 获取排序字符串 (用于数据库查询)
     */
    public function getOrderBy(): string
    {
        if (!empty($this->order)) {
            return $this->order;
        }

        if (!empty($this->sortField)) {
            return $this->sortField . ' ' . $this->getSortOrder();
        }

        return 'id desc';
    }
}
