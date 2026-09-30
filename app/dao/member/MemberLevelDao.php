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
namespace app\dao\member;

use core\foundation\base\BaseDao;
use app\enum\common\EnabledStatus;
use app\model\member\MemberLevel;

/**
 * 会员等级数据访问对象
 */
class MemberLevelDao extends BaseDao
{
    /**
     * 设置模型
     */
    protected function setModel(): string
    {
        return MemberLevel::class;
    }

//    /**
//     * 根据积分获取等级
//     */
//    public function getLevelByPoints(int $points): ?MemberLevel
//    {
//        return $this->query()
//            ->where('enabled', EnabledStatus::ENABLED->value)
//            ->where('min_points', '<=', $points)
//            ->where(function ($query) use ($points) {
//                $query->where('max_points', '>=', $points)
//                      ->orWhere('max_points', 0);
//            })
//            ->orderBy('level', 'desc')
//            ->first();
//    }
//
//    /**
//     * 获取下一个等级
//     */
//    public function getNextLevel(MemberLevel $currentLevel): ?MemberLevel
//    {
//        return $this->query()
//            ->where('enabled', EnabledStatus::ENABLED->value)
//            ->where('level', '>', $currentLevel->level)
//            ->orderBy('level', 'asc')
//            ->first();
//    }
//
//    /**
//     * 获取等级进度
//     */
//    public function getLevelProgress(MemberLevel $currentLevel, int $points): array
//    {
//        $nextLevel = $this->getNextLevel($currentLevel);
//
//        if (!$nextLevel) {
//            return [
//                'current_points' => $points,
//                'next_points' => 0,
//                'progress' => 100,
//                'is_max_level' => true,
//            ];
//        }
//
//        $currentRange = $currentLevel->max_points - $currentLevel->min_points;
//        $currentPoints = $points - $currentLevel->min_points;
//        $progress = $currentRange > 0 ? round(($currentPoints / $currentRange) * 100, 2) : 0;
//
//        return [
//            'current_points' => $points,
//            'next_points' => $nextLevel->min_points,
//            'progress' => min($progress, 100),
//            'is_max_level' => false,
//        ];
//    }
}