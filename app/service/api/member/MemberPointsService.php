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

use app\dao\member\MemberDao;
use app\dao\member\MemberPointsDao;
use app\adminapi\event\member\PointsChangedEvent;
use app\enum\member\PointSource;
use app\enum\member\PointType;
use core\foundation\base\BaseService;
use support\Container;
use support\Log;

class MemberPointsService extends BaseService
{

    public function __construct(
        private readonly MemberPointsDao $memberPointsDao,
        MemberDao                        $dao
    )
    {
        $this->dao = $dao;
    }

    /**
     * 增加会员积分
     *
     * @param int|string      $memberId  会员ID
     * @param int             $points    积分数量
     * @param string          $remark    备注
     * @param PointSource     $source    积分来源
     * @param int|string|null $relatedId 关联ID
     *
     * @return float 新积分余额
     * @throws \Exception
     */
    public function addPoints(
        int|string      $memberId,
        int             $points,
        string          $remark = '',
        PointSource     $source = PointSource::OTHER,
        int|string|null $relatedId = null,
        ?array          $extra = null
    ): float
    {
        $member = $this->dao->find($memberId);
        if (!$member) {
            throw new \Exception('会员不存在');
        }
        
        $oldPoints = $member->points;
        $newPoints = $oldPoints + $points;
        
        $event = new PointsChangedEvent(
            $memberId,
            $points,
            $source,
            PointType::INCREASE,
            $oldPoints,
            $newPoints,
            $remark,
            $relatedId,
            $extra
        );
        $event->dispatch();

        return $this->getPointsBalance($memberId);
    }

    /**
     * 减少会员积分
     *
     * @param int|string      $memberId  会员ID
     * @param int             $points    积分数量
     * @param string          $remark    备注
     * @param PointSource     $source    积分来源
     * @param int|string|null $relatedId 关联ID
     *
     * @return float 新积分余额
     * @throws \Exception
     */
    public function deductPoints(
        int|string      $memberId,
        int             $points,
        string          $remark = '',
        PointSource     $source = PointSource::OTHER,
        int|string|null $relatedId = null,
        ?array          $extra = null
    ): float
    {
        $member = $this->dao->find($memberId);
        if (!$member) {
            throw new \Exception('会员不存在');
        }
        
        $oldPoints = $member->points;
        if ($oldPoints < $points) {
            throw new \Exception('积分不足');
        }
        $newPoints = $oldPoints - $points;
        
        $event = new PointsChangedEvent(
            $memberId,
            $points,
            $source,
            PointType::DECREASE,
            $oldPoints,
            $newPoints,
            $remark,
            $relatedId,
            $extra
        );
        $event->dispatch();

        return $this->getPointsBalance($memberId);
    }

    /**
     * 获取会员积分记录
     *
     * @param int|string $memberId 会员ID
     * @param int        $limit    记录数量
     *
     * @return array 积分记录列表
     */
    public function getPointsRecords(int|string $memberId, int $limit = 20): array
    {
        return $this->memberPointsDao->getMemberPoints($memberId, $limit);
    }

    /**
     * 获取会员积分余额
     *
     * @param int|string $memberId 会员ID
     *
     * @return float 积分余额
     */
    public function getPointsBalance(int|string $memberId): float
    {
        return $this->memberPointsDao->getMemberBalance($memberId);
    }

    /**
     * 检查会员积分是否足够
     *
     * @param int|string $memberId 会员ID
     * @param int        $points   需要的积分数量
     *
     * @return bool 积分是否足够
     */
    public function isPointsEnough(int|string $memberId, int $points): bool
    {
        $balance = $this->getPointsBalance($memberId);
        return $balance >= $points;
    }

    /**
     * 获取积分流水记录（分页）
     *
     * @param array $params 查询参数
     *
     * @return array
     * @throws \Exception
     */
    public function getPointsRecord(array $params): array
    {
        $memberId = $params['member_id'] ?? 0;
        $page     = $params['page'] ?? 1;
        $limit    = $params['limit'] ?? 20;
        $type     = $params['type'] ?? null;

        if (!$memberId) {
            throw new \Exception('会员ID不能为空');
        }

        $offset = ($page - 1) * $limit;

        $query = $this->memberPointsDao->query()
            ->where('member_id', '=', $memberId);

        if ($type) {
            $query->where('type', '=', $type);
        }

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
     * 获取会员积分总额统计
     *
     * @param int|string $memberId 会员ID
     *
     * @return array
     * @throws \Exception
     */
    public function getPointsTotal(int|string $memberId): array
    {
        if (!$memberId) {
            throw new \Exception('会员ID不能为空');
        }

        $currentPoints = $this->getPointsBalance($memberId);

        $monthStart = date('Y-m-01 00:00:00');
        $monthEnd   = date('Y-m-t 23:59:59');

        $monthlyPoints = $this->memberPointsDao->query()
            ->where('member_id', '=', $memberId)
            ->where('type', '=', PointType::INCREASE->value)
            ->whereBetween('created_at', [$monthStart, $monthEnd])
            ->sum('points');

        $totalPoints = $this->memberPointsDao->query()
            ->where('member_id', '=', $memberId)
            ->where('type', '=', PointType::INCREASE->value)
            ->sum('points');

        return [
            'current_points' => (int)$currentPoints,
            'monthly_points' => (int)($monthlyPoints ?? 0),
            'total_points'   => (int)($totalPoints ?? 0),
        ];
    }

    /**
     * 获取会员等级信息
     *
     * @param int|string $memberId 会员ID
     *
     * @return array
     */
    public function getMemberLevel(int|string $memberId): array
    {
        if (!$memberId) {
            throw new \Exception('会员ID不能为空');
        }

        $member = $this->dao->find($memberId);

        if (!$member) {
            throw new \Exception('会员不存在');
        }

        return [
            'level_id' => $member->level_id,
            'points'   => $member->points,
        ];
    }

    /**
     * 会员签到获取积分
     *
     * @param int|string $memberId       会员ID
     * @param int        $continuousDays 连续签到天数
     *
     * @return int 获得的积分数量
     * @throws \Exception
     */
    public function getSignPoints(int|string $memberId, int $continuousDays): int
    {
        // 每次签到固定+1积分
        $points = 1;

        // 触发积分增加事件
        $this->addPoints(
            $memberId,
            $points,
            '每日签到',
            PointSource::SIGN_IN
        );

        return $points;
    }

    /**
     * 会员购物获取积分
     *
     * @param int|string $memberId 会员ID
     * @param float      $amount   购物金额
     * @param int|string $orderId  订单ID
     *
     * @return int 获得的积分数量
     * @throws \Exception
     */
    public function getShoppingPoints(int|string $memberId, float $amount, int|string $orderId): int
    {
        // 计算购物积分（例如：每消费10元获得1积分）
        $points = (int)($amount / 10);

        if ($points > 0) {
            $this->addPoints(
                $memberId,
                $points,
                '购物获得积分',
                PointSource::SHOPPING,
                $orderId
            );
        }

        return $points;
    }

    /**
     * 会员邀请获取积分
     *
     * @param int|string $memberId        会员ID
     * @param int|string $invitedMemberId 被邀请的会员ID
     *
     * @return int 获得的积分数量
     * @throws \Exception
     */
    public function getInvitePoints(int|string $memberId, int|string $invitedMemberId): int
    {
        // 邀请奖励积分
        $points = 50;

        $this->addPoints(
            $memberId,
            $points,
            '邀请好友注册',
            PointSource::INVITE,
            $invitedMemberId
        );

        return $points;
    }

    /**
     * 会员注册获取积分
     *
     * @param int|string $memberId 会员ID
     *
     * @return int 获得的积分数量
     * @throws \Exception
     */
    public function getRegisterPoints(int|string $memberId): int
    {
        // 注册奖励积分
        $points = 100;

        $this->addPoints(
            $memberId,
            $points,
            '新会员注册奖励',
            PointSource::REGISTER
        );

        return $points;
    }

    /**
     * 会员兑换商品扣减积分
     *
     * @param int|string $memberId  会员ID
     * @param int        $points    兑换需要的积分
     * @param int|string $productId 兑换商品ID
     *
     * @return float 扣减后的积分余额
     * @throws \Exception
     */
    public function deductExchangePoints(int|string $memberId, int $points, int|string $productId): float
    {
        return $this->deductPoints(
            $memberId,
            $points,
            '兑换商品',
            PointSource::EXCHANGE,
            $productId
        );
    }

    /**
     * 积分过期扣减
     *
     * @param int|string $memberId 会员ID
     * @param int        $points   过期的积分数量
     *
     * @return float 扣减后的积分余额
     * @throws \Exception
     */
    public function deductExpiredPoints(int|string $memberId, int $points): float
    {
        return $this->deductPoints(
            $memberId,
            $points,
            '积分过期',
            PointSource::EXPIRED
        );
    }

    /**
     * 违规处罚扣减积分
     *
     * @param int|string $memberId 会员ID
     * @param int        $points   处罚的积分数量
     * @param string     $reason   处罚原因
     *
     * @return float 扣减后的积分余额
     * @throws \Exception
     */
    public function deductPenaltyPoints(int|string $memberId, int $points, string $reason): float
    {
        return $this->deductPoints(
            $memberId,
            $points,
            '违规处罚: ' . $reason,
            PointSource::PENALTY
        );
    }
}