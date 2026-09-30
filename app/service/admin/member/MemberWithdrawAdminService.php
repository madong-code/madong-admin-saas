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

use app\dao\member\MemberWithdrawDao;
use app\enum\member\WithdrawStatus;
use core\foundation\base\BaseService;

/**
 * 提现管理服务
 */
class MemberWithdrawAdminService extends BaseService
{

    /**
     * 构造函数
     */
    public function __construct(MemberWithdrawDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 获取待审核提现列表
     */
    public function getPendingWithdraws(array $params = [])
    {
        return $this->dao->getPendingWithdraws($params);
    }

    /**
     * 审核提现
     */
    public function auditWithdraw(int $withdrawId, int $status, string $auditRemark = '')
    {
        $withdraw = $this->dao->find($withdrawId);
        if (!$withdraw) {
            throw new \Exception('提现记录不存在');
        }

        if (!in_array($status, [WithdrawStatus::APPROVED->value, WithdrawStatus::REJECTED->value])) {
            throw new \Exception('审核状态无效');
        }

        return $this->dao->updateStatus($withdraw, $status, $auditRemark);
    }

    /**
     * 获取提现记录详情
     */
    public function getWithdrawDetail(int $withdrawId)
    {
        $withdraw = $this->dao->find($withdrawId);
        if (!$withdraw) {
            throw new \Exception('提现记录不存在');
        }

        return $withdraw;
    }

    /**
     * 更新提现状态
     */
    public function updateWithdrawStatus(int $withdrawId, int $status)
    {
        $withdraw = $this->dao->find($withdrawId);
        if (!$withdraw) {
            throw new \Exception('提现记录不存在');
        }

        return $this->dao->updateStatus($withdraw, $status);
    }

    /**
     * 获取提现统计
     */
    public function getWithdrawStats(array $params = [])
    {
        $totalApply = $this->dao->getCount($params);
        $totalSuccess = $this->dao->getCount(array_merge($params, ['status' => WithdrawStatus::COMPLETED->value]));
        $totalRejected = $this->dao->getCount(array_merge($params, ['status' => WithdrawStatus::REJECTED->value]));

        return [
            'total_apply' => $totalApply,
            'total_success' => $totalSuccess,
            'total_rejected' => $totalRejected,
        ];
    }
}
