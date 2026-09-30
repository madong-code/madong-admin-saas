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

trait WithPagination
{
    #[OA\Property(
        property: 'page',
        description: '当前页码',
        type: 'integer|null',
        minimum: 1,
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

    public function getPage(): int
    {
        return $this->page ?? 1;
    }

    public function getLimit(): int
    {
        $limit = $this->limit ?? 15;
        return min(max($limit, 1), 100);
    }

    public function getOffset(): int
    {
        return ($this->getPage() - 1) * $this->getLimit();
    }
}
