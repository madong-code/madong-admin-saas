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
use app\model\member\MemberWithdraw;
use app\enum\member\WithdrawStatus;

/**
 * 会员提现DAO
 */
class MemberWithdrawDao extends BaseDao
{
    /**
     * 设置模型
     */
    protected function setModel(): string
    {
        return MemberWithdraw::class;
    }

    /**
     * 生成提现订单号
     */
    public function generateOrderSn(): string
    {
        return 'WD' . date('YmdHis') . substr(implode('', array_map('ord', str_split(substr(uniqid(), 7, 13), 1))), 0, 8);
    }

    /**
     * 创建提现记录
     */
    public function create(int $memberId, int $accountId, float $amount, float $fee, float $actualAmount, string $bankName, string $bankAccount, string $bankCardholder, string $orderSn, string $remark = ''): MemberWithdraw
    {
        $withdraw = $this->getModel();
        $withdraw->member_id = $memberId;
        $withdraw->account_id = $accountId;
        $withdraw->amount = $amount;
        $withdraw->fee = $fee;
        $withdraw->actual_amount = $actualAmount;
        $withdraw->status = WithdrawStatus::PENDING->value;
        $withdraw->bank_name = $bankName;
        $withdraw->bank_account = $bankAccount;
        $withdraw->bank_cardholder = $bankCardholder;
        $withdraw->order_sn = $orderSn;
        $withdraw->remark = $remark;
        $withdraw->save();

        return $withdraw;
    }

    /**
     * 根据订单号查找提现记录
     */
    public function findByOrderSn(string $orderSn): ?MemberWithdraw
    {
        return $this->query()
            ->where('order_sn', $orderSn)
            ->first();
    }

    /**
     * 更新提现状态
     */
    public function updateStatus(MemberWithdraw $withdraw, int $status, string $auditRemark = ''): bool
    {
        $withdraw->status = $status;
        if ($auditRemark) {
            $withdraw->audit_remark = $auditRemark;
        }
        $withdraw->audit_at = date('Y-m-d H:i:s');
        return $withdraw->save();
    }

    /**
     * 获取会员提现列表
     */
    public function getMemberWithdraws(int $memberId, array $params = []): array
    {
        $query = $this->query()->where('member_id', $memberId);

        // 状态筛选
        if (isset($params['status'])) {
            $query->where('status', $params['status']);
        }

        // 时间范围筛选
        if (isset($params['start_date']) && isset($params['end_date'])) {
            $query->whereBetween('created_at', [
                $params['start_date'] . ' 00:00:00',
                $params['end_date'] . ' 23:59:59'
            ]);
        }

        // 分页参数
        $page = $params['page'] ?? 1;
        $limit = $params['limit'] ?? 10;

        return $query->orderBy('created_at', 'desc')
            ->paginate($limit, ['*'], 'page', $page)
            ->toArray();
    }

    /**
     * 获取提现统计
     */
    public function getWithdrawStats(int $memberId): array
    {
        $totalApply = $this->count(['member_id' => $memberId]);
        $totalSuccess = $this->count([
            'member_id' => $memberId,
            'status' => WithdrawStatus::COMPLETED->value
        ]);
        $totalAmount = $this->query()
            ->where('member_id', $memberId)
            ->where('status', WithdrawStatus::COMPLETED->value)
            ->sum('actual_amount');

        return [
            'total_apply' => $totalApply,
            'total_success' => $totalSuccess,
            'total_amount' => $totalAmount,
        ];
    }

    /**
     * 获取待审核提现列表
     */
    public function getPendingWithdraws(array $params = []): array
    {
        $query = $this->query()
            ->where('status', WithdrawStatus::PENDING->value);

        // 会员ID筛选
        if (isset($params['member_id'])) {
            $query->where('member_id', $params['member_id']);
        }

        // 分页参数
        $page = $params['page'] ?? 1;
        $limit = $params['limit'] ?? 10;

        return $query->orderBy('created_at', 'desc')
            ->with('member')
            ->paginate($limit, ['*'], 'page', $page)
            ->toArray();
    }

    /**
     * 获取提现总数
     */
    public function getCount(array $params = []): int
    {
        $query = $this->query();

        // 会员ID筛选
        if (isset($params['member_id'])) {
            $query->where('member_id', $params['member_id']);
        }

        // 状态筛选
        if (isset($params['status'])) {
            $query->where('status', $params['status']);
        }

        return $query->count();
    }
}
