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

trait WithTimeRange
{
    #[OA\Property(
        property: 'start_time',
        description: '开始时间 (YYYY-MM-DD HH:mm:ss)',
        type: 'string',
        format: 'date-time',
        example: '2024-01-01 00:00:00',
        nullable: true
    )]
    public ?string $startTime = null;

    #[OA\Property(
        property: 'end_time',
        description: '结束时间 (YYYY-MM-DD HH:mm:ss)',
        type: 'string',
        format: 'date-time',
        example: '2024-12-31 23:59:59',
        nullable: true
    )]
    public ?string $endTime = null;

    public function getStartTime(): ?string
    {
        return $this->startTime;
    }

    public function getEndTime(): ?string
    {
        return $this->endTime;
    }

    public function hasTimeRange(): bool
    {
        return !empty($this->startTime) && !empty($this->endTime);
    }

    /**
     * 获取时间范围数组
     */
    public function getTimeRange(): array
    {
        return [
            'start' => $this->startTime,
            'end'   => $this->endTime,
        ];
    }

    /**
     * 验证时间范围是否有效
     */
    public function isValidTimeRange(): bool
    {
        if (!$this->hasTimeRange()) {
            return false;
        }

        $start = strtotime($this->startTime);
        $end = strtotime($this->endTime);

        return $start && $end && $start <= $end;
    }
}
