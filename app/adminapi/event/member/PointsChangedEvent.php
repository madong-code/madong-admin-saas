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
namespace app\adminapi\event\member;

use app\enum\member\PointType;
use app\enum\member\PointSource;
use core\foundation\base\BaseEvent;

/**
 * 积分变动事件
 *
 * 统一的积分变动事件，用于处理积分的增加和减少
 */
class PointsChangedEvent extends BaseEvent
{
    /**
     * 会员ID
     */
    public int|string $memberId;

    /**
     * 变动类型
     */
    public PointType $type;

    /**
     * 变动前积分
     */
    public int $oldPoints;

    /**
     * 变动后积分
     */
    public int $newPoints;

    /**
     * 变动的积分数量
     */
    public int $points;

    /**
     * 积分来源
     */
    public PointSource $source;

    /**
     * 变动原因/备注
     */
    public string $remark;

    /**
     * 关联ID（如订单ID、活动ID等，可选）
     */
    public int|string|null $relatedId;

    /**
     * 额外数据（可选，用于存储其他相关信息）
     */
    public ?array $extra;

    /**
     * 构造函数
     */
    public function __construct(
        int|string $memberId,
        int $points,
        PointSource $source,
        PointType $type,
        int $oldPoints,
        int $newPoints,
        string $remark = '',
        int|string|null $relatedId = null,
        ?array $extra = null
    ) {
        $this->memberId = $memberId;
        $this->points = $points;
        $this->source = $source;
        $this->type = $type;
        $this->oldPoints = $oldPoints;
        $this->newPoints = $newPoints;
        $this->remark = $remark;
        $this->relatedId = $relatedId;
        $this->extra = $extra;
    }

    public function getEventName(): string
    {
        return 'adminapi.points.changed';
    }
}
