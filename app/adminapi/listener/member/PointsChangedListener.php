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
namespace app\adminapi\listener\member;

use app\adminapi\event\member\PointsChangedEvent;
use app\adminapi\event\member\MemberLevelUpdatedEvent;
use app\dao\member\MemberDao;
use app\dao\member\MemberLevelDao;
use app\dao\member\MemberPointsDao;
use app\enum\member\PointType;
use app\enum\common\EnabledStatus;
use core\foundation\base\BaseListener;
use support\Container;

/**
 * 积分变动监听器
 *
 * 处理积分变动的事件：
 * 1. 更新会员积分
 * 2. 记录积分流水
 * 3. 触发会员等级更新事件
 */
class PointsChangedListener extends BaseListener
{
    protected function process($event): void
    {
        // 更新会员积分
        $this->updateMemberPoints($event);

        // 记录积分流水
        $this->recordPointsLog($event);

        // 触发会员等级更新事件
        $this->updateMemberLevel($event);
    }

    /**
     * 更新会员积分
     */
    private function updateMemberPoints(PointsChangedEvent $event): void
    {
        /** @var MemberDao $dao */
        $dao = Container::make(MemberDao::class);
        $member = $dao->get($event->memberId);

        if (!$member) {
            return;
        }

        $member->points = $event->newPoints;
        $member->save();
    }

    /**
     * 记录积分流水
     */
    private function recordPointsLog(PointsChangedEvent $event): void
    {
        /** @var MemberPointsDao $dao */
        $dao = Container::make(MemberPointsDao::class);

        $logData = [
            'member_id' => $event->memberId,
            'type' => $event->type->value,
            'points' => $event->points,
            'balance' => $event->newPoints,
            'remark' => $event->remark,
            'source' => $event->source->value ?? '',
        ];
        if ($event->relatedId !== null) {
            $logData['order_id'] = (string)$event->relatedId;
        }
        $dao->save($logData);
    }

    /**
     * 更新会员等级
     */
    private function updateMemberLevel(PointsChangedEvent $event): void
    {
        $memberId = $event->memberId;
        $newPoints = $event->newPoints;

        // 获取会员对象
        /** @var MemberDao $dao */
        $dao = Container::make(MemberDao::class);
        $member = $dao->get($memberId);

        if (!$member) {
            return;
        }

        $oldLevelId = $member->level_id;
        $oldLevelName = null;

        /** @var MemberLevelDao $memberLevelDao */
        $memberLevelDao = Container::make(MemberLevelDao::class);
        // 获取旧等级名称
        if ($oldLevelId) {
            $oldLevel = $memberLevelDao->get($oldLevelId);
            if ($oldLevel) {
                $oldLevelName = $oldLevel->name;
            }
        }

        // 根据新积分获取等级
        $newLevel = $memberLevelDao->query()
            ->where('enabled', EnabledStatus::ENABLED->value)
            ->where('min_points', '<=', $newPoints)
            ->where(function ($query) use ($newPoints) {
                $query->where('max_points', '>=', $newPoints)
                    ->orWhere('max_points', 0);
            })
            ->orderBy('level', 'desc')
            ->first();

        // 如果等级存在且与当前等级不同，更新等级
        if ($newLevel && $newLevel->id != $member->level_id) {
            $member->level_id = $newLevel->id;
            $member->save();

            $reason = match (true) {
                $event->type === PointType::INCREASE => 'points_increase',
                $event->type === PointType::DECREASE => 'points_deducted',
                default => 'points_adjust',
            };

            $levelEvent = new MemberLevelUpdatedEvent(
                $memberId,
                $oldLevelId,
                $newLevel->id,
                $oldLevelName,
                $newLevel->name,
                $reason,
                ''
            );
            $levelEvent->dispatch();
        }
    }
}
