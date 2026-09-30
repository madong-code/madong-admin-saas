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

trait WithSearch
{
    #[OA\Property(
        property: 'keyword',
        description: '关键词搜索',
        type: 'string',
        example: ''
    )]
    public ?string $keyword = null;

    public function getKeyword(): ?string
    {
        return $this->keyword;
    }

    public function hasKeyword(): bool
    {
        return !empty($this->keyword);
    }
}
