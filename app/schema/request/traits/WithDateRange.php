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

trait WithDateRange
{
    #[OA\Property(
        property: 'start_date',
        description: '开始日期 (YYYY-MM-DD)',
        type: 'string',
        format: 'date',
        example: '2024-01-01',
        nullable: true
    )]
    public ?string $startDate = null;

    #[OA\Property(
        property: 'end_date',
        description: '结束日期 (YYYY-MM-DD)',
        type: 'string',
        format: 'date',
        example: '2024-12-31',
        nullable: true
    )]
    public ?string $endDate = null;

    public function getStartDate(): ?string
    {
        return $this->startDate;
    }

    public function getEndDate(): ?string
    {
        return $this->endDate;
    }

    public function hasDateRange(): bool
    {
        return !empty($this->startDate) && !empty($this->endDate);
    }

    /**
     * 获取日期范围数组
     */
    public function getDateRange(): array
    {
        return [
            'start' => $this->startDate,
            'end'   => $this->endDate,
        ];
    }

    /**
     * 验证日期范围是否有效
     */
    public function isValidDateRange(): bool
    {
        if (!$this->hasDateRange()) {
            return false;
        }

        $start = strtotime($this->startDate);
        $end = strtotime($this->endDate);

        return $start && $end && $start <= $end;
    }
}
