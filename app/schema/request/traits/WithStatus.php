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

trait WithStatus
{
    #[OA\Property(
        property: 'status',
        description: '状态(0:禁用,1:启用)',
        type: 'integer',
        enum: [0, 1],
        example: 1,
        nullable: true
    )]
    public ?int $status = null;

    public function getStatus(): ?int
    {
        return $this->status;
    }

    public function hasStatus(): bool
    {
        return $this->status !== null;
    }

    public function isEnable(): bool
    {
        return $this->status === 1;
    }

    public function isDisable(): bool
    {
        return $this->status === 0;
    }

    public function setEnable(): void
    {
        $this->status = 1;
    }

    public function setDisable(): void
    {
        $this->status = 0;
    }
}
