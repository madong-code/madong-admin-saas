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

trait WithId
{
    #[OA\Property(
        property: 'id',
        description: '主键ID',
        type: 'integer',
        minimum: 1,
        example: 1
    )]
    public ?int $id = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function hasId(): bool
    {
        return !empty($this->id);
    }

    public function isUpdate(): bool
    {
        return $this->hasId();
    }

    public function isCreate(): bool
    {
        return !$this->hasId();
    }
}
