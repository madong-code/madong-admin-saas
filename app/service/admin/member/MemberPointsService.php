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

use app\dao\member\MemberDao;
use app\dao\member\MemberPointsDao;
use app\enum\member\PointType;
use app\enum\member\PointSource;
use app\adminapi\event\member\PointsChangedEvent;
use core\foundation\base\BaseService;
use core\foundation\exception\handler\AdminException;
use support\Container;

/**
 * 会员积分服务类
 */
class MemberPointsService extends BaseService
{

    /**
     * 构造方法
     */
    public function __construct(MemberPointsDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 积分操作
     *
     * 仅计算积分并派发事件，由事件监听器统一处理所有 DB 写入（更新会员积分、记录积分流水、更新等级）。
     * 参考 app\service\api\member\MemberPointsService 的模式。
     *
     * @throws \core\foundation\exception\handler\AdminException
     * @throws \Throwable
     */
    public function operate(array $data): void
    {
        try {
            // 1. 查询会员
            $memberDao = Container::make(MemberDao::class);
            $member = $memberDao->get($data['member_id']);
            if (!$member) {
                throw new AdminException('会员不存在');
            }

            $oldPoints = $member->points;
            $points = (int)$data['points'];
            $type   = (int)$data['type'];
            $remark = $data['remark'] ?? '';

            // 2. 计算新积分
            if ($type == PointType::INCREASE->value) {
                $newPoints = $oldPoints + $points;
            } elseif ($type == PointType::DECREASE->value) {
                if ($oldPoints < $points) {
                    throw new AdminException('积分不足');
                }
                $newPoints = $oldPoints - $points;
            } elseif ($type == PointType::ADJUST->value) {
                $newPoints = $points;
            } else {
                throw new AdminException('无效的积分类型');
            }

            // 3. 事务中只派发事件，由 Listener 统一处理 DB 写入
            $this->transaction(function () use ($member, $oldPoints, $newPoints, $points, $type, $remark) {
                $pointType = PointType::tryFrom($type) ?? PointType::INCREASE;

                $event = new PointsChangedEvent(
                    $member->id,
                    $points,
                    PointSource::ADMIN,
                    $pointType,
                    $oldPoints,
                    $newPoints,
                    $remark
                );
                $event->dispatch();
            });

        } catch (\Exception $e) {
            throw new AdminException($e->getMessage());
        }
    }
//
//    /**
//     * 批量积分操作
//     */
//    public function batchOperate(array $data): array
//    {
//        $memberIds = $data['member_ids'] ?? [];
//        if (empty($memberIds)) {
//            throw new AdminException('请选择会员');
//        }
//
//        $results = [];
//        foreach ($memberIds as $memberId) {
//            try {
//                $operateData = array_merge($data, ['member_id' => $memberId]);
//                $result = $this->operate($operateData);
//                $results[] = $result;
//            } catch (xception $e) {
//                $results[] = ['member_id' => $memberId, 'error' => $e->getMessage()];
//            }
//        }
//
//        return $results;
//    }
//
//    /**
//     * 获取积分统计
//     */
//    public function getStatistics(array $params): array
//    {
//        $startTime = $params['start_time'] ?? null;
//        $endTime = $params['end_time'] ?? null;
//
//        $where = [];
//        if ($startTime) {
//            $where[] = ['create_time', '>=', strtotime($startTime)];
//        }
//        if ($endTime) {
//            $where[] = ['create_time', '<=', strtotime($endTime) + 86399];
//        }
//
//        $totalIncome = $this->dao->query()->where($where)->where('type', 1)->sum('points') ?: 0;
//        $totalExpense = $this->dao->query()->where($where)->where('type', 2)->sum('points') ?: 0;
//        $totalRecords = $this->dao->query()->where($where)->count();
//
//        return [
//            'total_income' => $totalIncome,
//            'total_expense' => $totalExpense,
//            'net_income' => $totalIncome - $totalExpense,
//            'total_records' => $totalRecords,
//        ];
//    }
//
//    /**
//     * 设置积分规则
//     */
//    public function setRules(array $data): array
//    {
//        $rules = [
//            'sign_points' => $data['sign_points'] ?? 10,
//            'login_points' => $data['login_points'] ?? 5,
//            'max_sign_days' => $data['max_sign_days'] ?? 30,
//        ];
//
//        $config = $this->configDao->getByKey('member_points_rules');
//        if ($config) {
//            $config->value = json_encode($rules);
//            $config->save();
//        } else {
//            $this->configDao->save([
//                'key' => 'member_points_rules',
//                'value' => json_encode($rules),
//                'name' => '会员积分规则',
//                'group' => 'member',
//                'type' => 'json',
//            ]);
//        }
//
//        return $rules;
//    }
//
//    /**
//     * 获取积分规则
//     */
//    public function getRules(): array
//    {
//        $config = $this->configDao->getByKey('member_points_rules');
//        if ($config) {
//            return json_decode($config->value, true) ?: [];
//        }
//
//        // 默认规则
//        return [
//            'sign_points' => 10,
//            'login_points' => 5,
//            'max_sign_days' => 30,
//        ];
//    }
}
