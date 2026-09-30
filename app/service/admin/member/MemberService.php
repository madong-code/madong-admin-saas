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
use app\enum\member\PointSource;
use app\enum\member\PointType;
use app\adminapi\event\member\PointsChangedEvent;
use core\foundation\base\BaseService;
use core\foundation\exception\handler\AdminException;
use support\Container;

/**
 * 会员服务类
 * @method getUsersListByTagId(mixed $where, mixed $field, mixed $page, mixed $limit)
 * @method getUsersExcludingTag(mixed $where, mixed $field, mixed $page, mixed $limit)
 */
class MemberService extends BaseService
{
    /**
     * 构造方法
     */
    public function __construct(MemberDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 分配标签
     *
     * @throws \core\exception\handler\AdminException
     */
    public function assignTags(int|string $id, array $tags = []): void
    {
        try {
            $model = $this->dao->get($id);
            if (empty($model)) {
                throw new AdminException('会员不存在');
            }
            $model->tags()->sync($tags);
        } catch (\Exception $exception) {
            throw new AdminException($exception->getMessage());
        }
    }

    /**
     * 调整会员积分（使用 Laravel ORM）
     *
     * @param int|string $id     会员ID
     * @param int        $points 积分变动值（正数增加，负数减少）
     * @param array      $options
     *
     * @throws \Throwable
     * @throws \core\exception\handler\AdminException
     */
    public function adjustPoints(int|string $id, int $points, array $options = []): void
    {
        try {
            $this->transaction(function () use ($id, $points, $options) {
                // 使用 Laravel ORM 查询会员
                $member = $this->dao->get($id);
                if (!$member) {
                    throw new AdminException('会员不存在');
                }
                // 检查积分是否足够（如果是减少积分）
                if ($points < 0 && $member->points < abs($points)) {
                    throw new AdminException('会员积分不足');
                }

                // 计算新积分
                $newPoints = abs($points);
                // 积分不能小于0
                if ($newPoints < 0) {
                    throw new AdminException('积分不能小于0');
                }
                //添加积分记录
                $pointsService = Container::make(MemberPointsService::class);
                $params        = [
                    'member_id' => $member->id,
                    'points'    => $points,
                    'balance'   => $newPoints,
                    'type'      => $options['type'] ?? PointType::ADJUST->value,
                    'source'    => $options['source'] ?? PointSource::ADMIN->value,
                    'remark'    => $options['remark'] ?? '',
                ];
                $pointsService->save($params);
                $member->update(['points' => $newPoints]);
                
                $source = PointSource::ADMIN;
                if (!empty($params['source'])) {
                    $source = PointSource::tryFrom($params['source']) ?? PointSource::ADMIN;
                }
                
                $event = new PointsChangedEvent(
                    $member->id,
                    $points,
                    $source,
                    PointType::INCREASE,
                    $member->points,
                    $newPoints,
                    $options['remark'] ?? ''
                );
                $event->dispatch();
            });
        } catch (\Throwable $exception) {
            throw new AdminException('调整积分失败: ' . $exception->getMessage());
        }
    }

}