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
 * Official Website: https://madong.tech
 */

namespace app\service\api\member;

use app\dao\member\MemberDao;
use app\dao\member\MemberSignDao;
use app\service\api\member\MemberPointsService;
use app\model\member\MemberSign;
use app\enum\member\PointSource;
use core\foundation\base\BaseService;
use core\foundation\exception\handler\BadRequestHttpException;

/**
 * 会员签到服务
 */
class MemberSignService extends BaseService
{

    public function __construct(
        MemberDao                   $dao,
        private MemberSignDao       $memberSignDao,
        private MemberPointsService $memberPointsService
    )
    {
        $this->dao = $dao;
    }

    /**
     * 执行签到
     *
     * @throws \Exception
     * @throws \Throwable
     */
    public function sign(int|string $memberId, array $deviceInfo = []): array
    {
        $today = date('Y-m-d');

        // 检查今日是否已签到
        if ($this->memberSignDao->isSignedToday($memberId, $today)) {
            throw new BadRequestHttpException('今日已签到');
        }

        return $this->transaction(function () use ($memberId, $deviceInfo, $today) {

            // 获取最近一次签到记录
            $lastSign = $this->memberSignDao->getLastSign($memberId);

            // 计算连续签到天数
            $continuousDays = 1;
            if ($lastSign) {
                $lastSignDate = $lastSign->sign_date;
                $yesterday    = date('Y-m-d', strtotime('-1 day'));

                if ($lastSignDate === $yesterday) {
                    $continuousDays = $lastSign->continuous_days + 1;
                }
            }

            // 创建签到记录
            $signRecord = $this->memberSignDao->created(
                $memberId,
                $today,
                0, // 暂时设为0，后续会通过积分服务更新
                $continuousDays,
                $deviceInfo
            );

            // 增加会员积分（使用积分服务）
            $points = $this->memberPointsService->getSignPoints((int)$memberId, $continuousDays);

            return [
                'points'          => $points,
                'continuous_days' => $continuousDays,
                'sign_date'       => $today,
                'sign_record'     => $signRecord,
            ];
        });
    }

    /**
     * 获取签到状态
     */
    public function getStatus(int|string $memberId): array
    {
        $statistics = $this->memberSignDao->getSignStatistics($memberId);

        return [
            'is_signed_today' => $statistics['today_signed'],
            'continuous_days' => $statistics['continuous_days'],
            'total_sign_days' => $statistics['total_sign_days'],
            'month_sign_days' => $statistics['month_sign_days'],
            'today'           => date('Y-m-d'),
            'points'          => $statistics['points'] ?? 0,
        ];
    }

    /**
     * 获取签到日历
     */
    public function getCalendar(int|string $memberId, ?int $year = null, ?int $month = null): array
    {
        $year  = $year ?? (int)date('Y');
        $month = $month ?? (int)date('m');

        $calendar = $this->memberSignDao->getSignCalendar($memberId, $year, $month);

        return [
            'year'     => $year,
            'month'    => $month,
            'calendar' => $calendar,
        ];
    }

    /**
     * 获取签到统计
     */
    public function getStatistics(int|string $memberId, string $type = 'month'): array
    {
        $statistics = [];

        switch ($type) {
            case 'week':
                $statistics = $this->memberSignDao->getWeekSignStatistics($memberId);
                break;
            case 'year':
                $statistics = $this->memberSignDao->getYearSignStatistics($memberId);
                break;
            case 'month':
            default:
                $basicStats = $this->memberSignDao->getSignStatistics($memberId);
                $statistics = [
                    'total_sign_days' => $basicStats['total_sign_days'],
                    'month_sign_days' => $basicStats['month_sign_days'],
                    'continuous_days' => $basicStats['continuous_days'],
                    'today_signed'    => $basicStats['today_signed'],
                ];
                break;
        }

        return $statistics;
    }

    /**
     * 检查今日是否已签到
     */
    public function isSignedToday(int|string $memberId): bool
    {
        return $this->memberSignDao->isSignedToday($memberId, date('Y-m-d'));
    }

    /**
     * 获取连续签到天数
     */
    public function getContinuousDays(int|string $memberId): int
    {
        $lastSign = $this->memberSignDao->getLastSign($memberId);

        if (!$lastSign) {
            return 0;
        }

        $lastSignDate = $lastSign->sign_date;
        $yesterday    = date('Y-m-d', strtotime('-1 day'));

        if ($lastSignDate === date('Y-m-d')) {
            // 今日已签到
            return $lastSign->continuous_days;
        } elseif ($lastSignDate === $yesterday) {
            // 昨日已签到
            return $lastSign->continuous_days;
        } else {
            // 连续签到中断
            return 0;
        }
    }

    /**
     * 获取总签到天数
     */
    public function getTotalSignDays(int|string $memberId): int
    {
        $statistics = $this->memberSignDao->getSignStatistics($memberId);
        return $statistics['total_sign_days'] ?? 0;
    }

    /**
     * 获取本月签到天数
     */
    public function getMonthSignDays(int|string $memberId): int
    {
        return $this->memberSignDao->getMonthSignDays($memberId);
    }

    /**
     * 每月最大补签次数
     */
    private const MAX_RESIGN_PER_MONTH = 3;


    /**
     * 补签：补签指定日期
     *
     * @throws \Exception
     * @throws \Throwable
     */
    public function reSign(int|string $memberId, string $signDate): array
    {
        $today = date('Y-m-d');

        // 校验日期合法性
        if ($signDate >= $today) {
            throw new BadRequestHttpException('只能补签今天之前的日期');
        }

        // 必须是本月
        $signMonth = substr($signDate, 0, 7);
        $thisMonth = date('Y-m');
        if ($signMonth !== $thisMonth) {
            throw new BadRequestHttpException('只能补签本月内的日期');
        }

        // 检查是否已签到
        if ($this->memberSignDao->isSignedToday($memberId, $signDate)) {
            throw new BadRequestHttpException('该日期已签到，无需重复补签');
        }

        // 检查本月补签次数上限
        $monthReSignCount = $this->memberSignDao->getMonthReSignCount($memberId);
        if ($monthReSignCount >= self::MAX_RESIGN_PER_MONTH) {
            throw new BadRequestHttpException('本月补签次数已用完（每月最多' . self::MAX_RESIGN_PER_MONTH . '次）');
        }

        // 补签日期必须与已签到日期相邻
        if (!$this->memberSignDao->hasAdjacentSign($memberId, $signDate)) {
            throw new BadRequestHttpException('补签日期必须与已签到日期相邻');
        }

        return $this->transaction(function () use ($memberId, $signDate) {
            $signRecord = $this->memberSignDao->reSign(
                $memberId,
                $signDate,
                0,
                0
            );

            // 重新计算连续签到天数并更新记录
            $continuousDays = $this->memberSignDao->recalcContinuousDays($memberId);

            // 更新补签记录的积分和连续天数
            $signRecord->continuous_days = $continuousDays;
            $signRecord->save();

            // 增加积分
            $points = $this->memberPointsService->getSignPoints((int)$memberId, $continuousDays);

            return [
                'points'          => $points,
                'continuous_days' => $continuousDays,
                'sign_date'       => $signDate,
                'sign_record'     => $signRecord,
            ];
        });
    }
}