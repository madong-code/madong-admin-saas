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
namespace app\service\api\member;

use app\api\CurrentMember;
use app\dao\member\MemberBillDao;
use core\foundation\base\BaseService;
use support\Container;

/**
 * 会员余额服务
 */
class MemberBalanceService extends BaseService
{
    public function __construct(MemberBillDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 获取余额流水记录
     */
    public function getBalanceRecords(array $params = []): array
    {
        $currentMember = Container::make(CurrentMember::class);
        $member = $currentMember->user(true);
        if (empty($member)) {
            throw new \Exception('用户凭证失效请重新登录', 401);
        }

        return $this->dao->getMemberBills((int) $member['id'], $params);
    }

    /**
     * 获取所有余额流水记录（分页）
     */
    public function getAllBalanceRecords(array $params = []): array
    {
        $currentMember = Container::make(CurrentMember::class);
        $member = $currentMember->user(true);
        if (empty($member)) {
            throw new \Exception('用户凭证失效请重新登录', 401);
        }

        return $this->dao->getMemberBills((int) $member['id'], $params);
    }
}
