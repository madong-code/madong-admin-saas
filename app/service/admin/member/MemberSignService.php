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
namespace app\service\admin\member;

use app\dao\member\MemberSignDao;
use core\foundation\base\BaseService;

/**
 * 会员签到服务类
 */
class MemberSignService extends BaseService
{
    /**
     * 构造方法
     */
    public function __construct(MemberSignDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 获取会员签到统计
     */
    public function getMemberSignStatistics(int $memberId): array
    {
        return $this->dao->getSignStatistics($memberId);
    }

    /**
     * 获取会员签到记录
     */
    public function getMemberSignRecords(int $memberId, int $days = 30): array
    {
        return $this->dao->getMemberSignRecords($memberId, $days);
    }

    /**
     * 手动为会员签到
     */
    public function manualSign(int $memberId, int $points = 10): array
    {
        return $this->dao->create($memberId, date('Y-m-d'), $points, 1);
    }

    /**
     * 获取签到日历数据
     */
    public function getSignCalendar(int $memberId, string $yearMonth): array
    {
        $year = substr($yearMonth, 0, 4);
        $month = substr($yearMonth, 5, 2);
        
        $startDate = "{$year}-{$month}-01";
        $endDate = date('Y-m-t', strtotime($startDate));
        
        $signRecords = $this->dao->where('member_id', $memberId)
            ->whereBetween('sign_date', [$startDate, $endDate])
            ->pluck('sign_date')
            ->toArray();
        
        $calendar = [];
        $currentDate = $startDate;
        
        while ($currentDate <= $endDate) {
            $calendar[] = [
                'date' => $currentDate,
                'signed' => in_array($currentDate, $signRecords),
                'day' => date('d', strtotime($currentDate))
            ];
            $currentDate = date('Y-m-d', strtotime($currentDate . ' +1 day'));
        }
        
        return $calendar;
    }
}