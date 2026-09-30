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

use core\foundation\base\BaseEvent;

/**
 * 会员等级更新事件
 *
 * 用于处理会员等级变动的场景：
 * - 积分增加导致等级提升
 * - 积分扣减导致等级下降
 * - 手动调整会员等级
 */
class MemberLevelUpdatedEvent extends BaseEvent
{
    /**
     * 会员ID
     */
    public int|string $memberId;

    /**
     * 变动前等级ID
     */
    public int|string|null $oldLevelId;

    /**
     * 变动后等级ID
     */
    public int|string|null $newLevelId;

    /**
     * 变动前等级名称
     */
    public ?string $oldLevelName;

    /**
     * 变动后等级名称
     */
    public ?string $newLevelName;

    /**
     * 触发原因（points_increase=积分增加, points_deducted=积分扣减, admin=后台调整, other=其他）
     */
    public string $reason;

    /**
     * 备注
     */
    public string $remark;

    /**
     * 额外数据（可选，用于存储其他相关信息）
     */
    public ?array $extra;

    /**
     * 构造函数
     */
    public function __construct(
        int|string $memberId,
        int|string|null $oldLevelId,
        int|string|null $newLevelId,
        ?string $oldLevelName,
        ?string $newLevelName,
        string $reason,
        string $remark = '',
        ?array $extra = null
    ) {
        $this->memberId = $memberId;
        $this->oldLevelId = $oldLevelId;
        $this->newLevelId = $newLevelId;
        $this->oldLevelName = $oldLevelName;
        $this->newLevelName = $newLevelName;
        $this->reason = $reason;
        $this->remark = $remark;
        $this->extra = $extra;
    }

    public function getEventName(): string
    {
        return 'adminapi.member.level.updated';
    }
}
