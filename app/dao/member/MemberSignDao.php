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

use app\service\api\member\MemberService;
use core\foundation\base\BaseDao;
use app\model\member\MemberSign;
use support\Container;

/**
 * 会员签到DAO
 */
class MemberSignDao extends BaseDao
{
    /**
     * 设置模型
     */
    protected function setModel(): string
    {
        return MemberSign::class;
    }
    
    /**
     * 创建签到记录
     */
    public function created(int|string $memberId, string $signDate, int $points, int $continuousDays, array $deviceInfo = []): MemberSign
    {
        $sign = new MemberSign();
        $sign->member_id = $memberId;
        $sign->sign_date = $signDate;
        $sign->points = $points;
        $sign->continuous_days = $continuousDays;
        
        $sign->save();

        return $sign;
    }

    /**
     * 检查今日是否已签到
     */
    public function isSignedToday(int|string $memberId, string $today): bool
    {
        return $this->query()
            ->where('member_id', $memberId)
            ->where('sign_date', $today)
            ->exists();
    }

    /**
     * 获取最近一次签到记录
     */
    public function getLastSign(int|string $memberId): ?MemberSign
    {
        return $this->query()
            ->where('member_id', $memberId)
            ->orderBy('sign_date', 'desc')
            ->first();
    }

    /**
     * 获取会员签到记录
     */
    public function getMemberSignRecords(int|string $memberId, int $days = 30): array
    {
        $startDate = date('Y-m-d', strtotime("-$days days"));
        
        return $this->query()
            ->where('member_id', $memberId)
            ->where('sign_date', '>=', $startDate)
            ->orderBy('sign_date', 'asc')
            ->get()
            ->toArray();
    }

    /**
     * 获取本月签到天数
     */
    public function getMonthSignDays(int|string $memberId): int
    {
        $firstDay = date('Y-m-01');
        $lastDay = date('Y-m-t');
        
        return $this->query()
            ->where('member_id', $memberId)
            ->whereBetween('sign_date', [$firstDay, $lastDay])
            ->count();
    }

    /**
     * 获取连续签到排行榜
     */
    public function getContinuousSignRanking(int $limit = 10): array
    {
        return $this->query()
            ->selectRaw('member_id, MAX(continuous_days) as max_continuous_days')
            ->groupBy('member_id')
            ->orderBy('max_continuous_days', 'desc')
            ->limit($limit)
            ->with('member')
            ->get()
            ->toArray();
    }

    /**
     * 获取签到统计
     */
    public function getSignStatistics(int|string $memberId): array
    {
        $totalSignDays = $this->query()
            ->where('member_id', $memberId)
            ->count();
        $monthSignDays = $this->getMonthSignDays($memberId);
        $continuousDays = $this->query()
            ->where('member_id', $memberId)
            ->orderBy('sign_date', 'desc')
            ->value('continuous_days') ?? 0;

        // 获取用户积分
        $points = Container::make(MemberService::class)
            ->get($memberId)
            ->points ?? 0;

        return [
            'total_sign_days' => $totalSignDays,
            'month_sign_days' => $monthSignDays,
            'continuous_days' => $continuousDays,
            'today_signed' => $this->isSignedToday($memberId, date('Y-m-d')),
            'points' => $points
        ];
    }

    /**
     * 获取年度签到统计
     */
    public function getYearSignStatistics(int|string $memberId, ?int $year = null): array
    {
        if ($year === null) {
            $year = date('Y');
        }

        $startDate = $year . '-01-01';
        $endDate = $year . '-12-31';

        $totalSignDays = $this->query()
            ->where('member_id', $memberId)
            ->whereBetween('sign_date', [$startDate, $endDate])
            ->count();

        // 统计每个月的签到天数
        $monthlyStats = [];
        for ($month = 1; $month <= 12; $month++) {
            $monthStart = $year . '-' . str_pad((string)$month, 2, '0', STR_PAD_LEFT) . '-01';
            $monthEnd = $year . '-' . str_pad((string)$month, 2, '0', STR_PAD_LEFT) . '-' . date('t', strtotime($monthStart));

            $monthlyStats[$month] = $this->query()
                ->where('member_id', $memberId)
                ->whereBetween('sign_date', [$monthStart, $monthEnd])
                ->count();
        }

        return [
            'year' => $year,
            'total_sign_days' => $totalSignDays,
            'monthly_stats' => $monthlyStats
        ];
    }

    /**
     * 获取周签到统计
     */
    public function getWeekSignStatistics(int|string $memberId): array
    {
        $weekStart = date('Y-m-d', strtotime('this week'));
        $weekEnd = date('Y-m-d', strtotime('this week +6 days'));

        $signDays = $this->query()
            ->where('member_id', $memberId)
            ->whereBetween('sign_date', [$weekStart, $weekEnd])
            ->count();

        return [
            'week_start' => $weekStart,
            'week_end' => $weekEnd,
            'sign_days' => $signDays,
            'total_days' => 7
        ];
    }

    /**
     * 获取连续签到奖励
     */
    public function getContinuousSignReward(int|string $continuousDays): int
    {
        // 连续签到奖励规则
        $rewards = [
            7 => 50,   // 连续7天奖励50积分
            14 => 100, // 连续14天奖励100积分
            30 => 200, // 连续30天奖励200积分
        ];

        return $rewards[$continuousDays] ?? 0;
    }

    /**
     * 获取签到日历
     */
    public function getSignCalendar(int|string $memberId, int $year, int $month): array
    {
        $firstDay = $year . '-' . str_pad((string)$month, 2, '0', STR_PAD_LEFT) . '-01';
        $lastDay = $year . '-' . str_pad((string)$month, 2, '0', STR_PAD_LEFT) . '-' . date('t', strtotime($firstDay));

        $signs = $this->query()
            ->where('member_id', $memberId)
            ->whereBetween('sign_date', [$firstDay, $lastDay])
            ->get()
            ->toArray();

        $calendar = [];
        foreach ($signs as $sign) {
            $calendar[] = $sign['sign_date'];
        }

        return $calendar;
    }

    /**
     * 批量获取会员签到统计
     */
    public function batchGetSignStatistics(array $memberIds): array
    {
        $result = [];
        foreach ($memberIds as $memberId) {
            $result[$memberId] = $this->getSignStatistics($memberId);
        }
        return $result;
    }

    /**
     * 清理过期签到记录（保留最近一年的记录）
     */
    public function cleanExpiredRecords(): int
    {
        $oneYearAgo = date('Y-m-d', strtotime('-1 year'));
        return $this->query()
            ->where('sign_date', '<', $oneYearAgo)
            ->delete();
    }

    /**
     * 补签：在指定日期创建签到记录
     */
    public function reSign(int|string $memberId, string $signDate, int $points, int $continuousDays): MemberSign
    {
        $sign = new MemberSign();
        $sign->member_id       = $memberId;
        $sign->sign_date       = $signDate;
        $sign->points          = $points;
        $sign->continuous_days = $continuousDays;
        $sign->is_resign       = 1;
        $sign->save();

        return $sign;
    }

    /**
     * 获取本月补签次数
     */
    public function getMonthReSignCount(int|string $memberId): int
    {
        $firstDay = date('Y-m-01');
        $lastDay  = date('Y-m-t');

        return $this->query()
            ->where('member_id', $memberId)
            ->where('is_resign', 1)
            ->whereBetween('sign_date', [$firstDay, $lastDay])
            ->count();
    }

    /**
     * 检查指定日期是否有相邻的已签到日期
     */
    public function hasAdjacentSign(int|string $memberId, string $signDate): bool
    {
        $prevDay = date('Y-m-d', strtotime($signDate . ' -1 day'));
        $nextDay = date('Y-m-d', strtotime($signDate . ' +1 day'));

        return $this->query()
            ->where('member_id', $memberId)
            ->whereIn('sign_date', [$prevDay, $nextDay])
            ->exists();
    }

    /**
     * 重新计算最近一次连续签到天数（用于补签后修复连续性）
     */
    public function recalcContinuousDays(int|string $memberId): int
    {
        $records = $this->query()
            ->where('member_id', $memberId)
            ->where('sign_date', '<=', date('Y-m-d'))
            ->orderBy('sign_date', 'desc')
            ->get();

        if ($records->isEmpty()) {
            return 0;
        }

        // 从最近签到日期往前推，统计连续天数
        $continuous = 0;
        $lastDate   = null;

        foreach ($records as $record) {
            $currentDate = $record->sign_date;
            $recordId    = $record->id;

            if ($lastDate === null) {
                $continuous = 1;
                $lastDate   = $currentDate;
                continue;
            }

            // 检查是否连续（相差一天）
            $expectedPrev = date('Y-m-d', strtotime($currentDate . ' +1 day'));
            if ($expectedPrev === $lastDate) {
                $continuous++;
                $lastDate = $currentDate;
            } else {
                break; // 中断则停止
            }
        }

        // 更新所有记录的 continuous_days
        $pos = $continuous;
        $updateRecords = $this->query()
            ->where('member_id', $memberId)
            ->where('sign_date', '<=', date('Y-m-d'))
            ->orderBy('sign_date', 'desc')
            ->limit($continuous)
            ->get();

        foreach ($updateRecords as $r) {
            $r->continuous_days = $pos;
            $r->save();
            $pos--;
        }

        return $continuous;
    }
}
