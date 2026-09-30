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

use app\dao\member\MemberBillDao;
use app\dao\member\MemberDao;
use core\foundation\base\BaseService;
use core\foundation\exception\handler\AdminException;

/**
 * 会员账单服务类
 */
class MemberBillService extends BaseService
{

    /**
     * 构造方法
     */
    public function __construct(MemberBillDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 余额操作
     */
    public function operate(array $data): array
    {
        $member = $this->memberDao->get($data['member_id']);
        if (!$member) {
            throw new AdminException('会员不存在');
        }

        $amount = (float)$data['amount'];
        $type = (int)$data['type'];
        $remark = $data['remark'] ?? '';

        // 开始事务
        $this->dao->getModel()->getConnection()->beginTransaction();
        try {
            // 更新会员余额
            if ($type == 1) {
                $member->balance += $amount;
            } else {
                if ($member->balance < $amount) {
                    throw new AdminException('余额不足');
                }
                $member->balance -= $amount;
            }
            $member->save();

            // 记录账单
            $billData = [
                'member_id' => $data['member_id'],
                'amount' => $amount,
                'type' => $type,
                'balance' => $member->balance,
                'remark' => $remark,
                'create_time' => time(),
            ];
            $bill = $this->dao->save($billData);

            $this->dao->getModel()->getConnection()->commit();
            return $bill->toArray();
        } catch (\Exception $e) {
            throw new AdminException($e->getMessage(), [], $e->getCode());
        }
    }

    /**
     * 批量余额操作
     */
    public function batchOperate(array $data): array
    {
        $memberIds = $data['member_ids'] ?? [];
        if (empty($memberIds)) {
            throw new AdminException('请选择会员');
        }

        $results = [];
        foreach ($memberIds as $memberId) {
            try {
                $operateData = array_merge($data, ['member_id' => $memberId]);
                $result = $this->operate($operateData);
                $results[] = $result;
            } catch (xception $e) {
                $results[] = ['member_id' => $memberId, 'error' => $e->getMessage()];
            }
        }

        return $results;
    }

    /**
     * 获取余额统计
     */
    public function getStatistics(array $params): array
    {
        $startTime = $params['start_time'] ?? null;
        $endTime = $params['end_time'] ?? null;

        $where = [];
        if ($startTime) {
            $where[] = ['create_time', '>=', strtotime($startTime)];
        }
        if ($endTime) {
            $where[] = ['create_time', '<=', strtotime($endTime) + 86399];
        }

        $totalIncome = $this->dao->query()->where($where)->where('type', 1)->sum('amount') ?: 0;
        $totalExpense = $this->dao->query()->where($where)->where('type', 2)->sum('amount') ?: 0;
        $totalRecords = $this->dao->query()->where($where)->count();

        return [
            'total_income' => $totalIncome,
            'total_expense' => $totalExpense,
            'net_income' => $totalIncome - $totalExpense,
            'total_records' => $totalRecords,
        ];
    }
}
