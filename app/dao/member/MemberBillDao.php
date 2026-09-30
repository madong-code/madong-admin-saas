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
use app\model\member\MemberBill;
use app\enum\member\BillType;
use app\enum\member\BillStatus;

/**
 * 会员账单DAO
 */
class MemberBillDao extends BaseDao
{
    /**
     * 设置模型
     */
    protected function setModel(): string
    {
        return MemberBill::class;
    }

    /**
     * 记录账单
     */
    public function record(int $memberId, int $type, int $category, float $amount, string $description, string $orderSn = '', int $status = BillStatus::SUCCESS->value): MemberBill
    {
        $bill = $this->getModel();
        $bill->member_id = $memberId;
        $bill->type = $type;
        $bill->category = $category;
        $bill->amount = $amount;
        $bill->description = $description;
        $bill->order_sn = $orderSn;
        $bill->status = $status;
        $bill->save();

        return $bill;
    }

    /**
     * 获取会员账单列表
     */
    public function getMemberBills(int $memberId, array $params = []): array
    {
        $query = $this->query()->where('member_id', $memberId);

        // 类型筛选
        if (isset($params['type'])) {
            $query->where('type', $params['type']);
        }

        // 分类筛选
        if (isset($params['category'])) {
            $query->where('category', $params['category']);
        }

        // 时间范围筛选
        if (isset($params['start_date']) && isset($params['end_date'])) {
            $query->whereBetween('created_at', [
                $params['start_date'] . ' 00:00:00',
                $params['end_date'] . ' 23:59:59'
            ]);
        }

        // 状态筛选
        if (isset($params['status'])) {
            $query->where('status', $params['status']);
        }

        // 分页参数
        $page  = intval($params['page'] ?? 1);
        $limit = intval($params['limit'] ?? 10);
        $offset = ($page - 1) * $limit;

        $total = $query->count();
        $items = $query->orderBy('created_at', 'desc')
            ->offset($offset)
            ->limit($limit)
            ->get()
            ->toArray();

        return [
            'items' => $items,
            'total' => $total,
            'page'  => $page,
            'limit' => $limit,
        ];
    }

    /**
     * 获取会员账单统计
     */
    public function getMemberBillStats(int $memberId, string $startDate, string $endDate): array
    {
        $stats = $this->query()
            ->where('member_id', $memberId)
            ->whereBetween('created_at', [
                $startDate . ' 00:00:00',
                $endDate . ' 23:59:59'
            ])
            ->selectRaw('type, category, SUM(amount) as total_amount, COUNT(*) as count')
            ->groupBy('type', 'category')
            ->get()
            ->toArray();

        $result = [
            'total_income' => 0,
            'total_expense' => 0,
            'category_stats' => [],
        ];

        foreach ($stats as $stat) {
            if ($stat['type'] == BillType::INCOME->value) {
                $result['total_income'] += $stat['total_amount'];
            } else {
                $result['total_expense'] += $stat['total_amount'];
            }

            $result['category_stats'][$stat['category']] = [
                'total_amount' => $stat['total_amount'],
                'count' => $stat['count'],
            ];
        }

        return $result;
    }

    /**
     * 获取最新账单
     */
    public function getLatestBill(int $memberId): ?MemberBill
    {
        return $this->query()
            ->where('member_id', $memberId)
            ->orderBy('created_at', 'desc')
            ->first();
    }

    /**
     * 获取今日账单统计
     */
    public function getTodayStats(int $memberId): array
    {
        $today = date('Y-m-d');
        return $this->getMemberBillStats($memberId, $today, $today);
    }

    /**
     * 获取本月账单统计
     */
    public function getMonthStats(int $memberId): array
    {
        $firstDay = date('Y-m-01');
        $lastDay = date('Y-m-t');
        return $this->getMemberBillStats($memberId, $firstDay, $lastDay);
    }

    /**
     * 根据订单号更新账单状态
     */
    public function updateStatusByOrderSn(string $orderSn, int $status): bool
    {
        return $this->query()
            ->where('order_sn', $orderSn)
            ->update(['status' => $status]);
    }

    /**
     * 获取账单总数
     */
    public function getMemberBillCount(int $memberId, array $params = []): int
    {
        $query = $this->query()->where('member_id', $memberId);

        // 类型筛选
        if (isset($params['type'])) {
            $query->where('type', $params['type']);
        }

        // 分类筛选
        if (isset($params['category'])) {
            $query->where('category', $params['category']);
        }

        // 状态筛选
        if (isset($params['status'])) {
            $query->where('status', $params['status']);
        }

        return $query->count();
    }
}
