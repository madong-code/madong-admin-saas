<?php
declare(strict_types=1);

/**
 *+------------------
 * madong - 审核操作日志服务
 *+------------------
 * Copyright (c) https://gitee.com/motion-code  All rights reserved.
 *+------------------
 * Author: Mr. April (405784684@qq.com)
 *+------------------
 * Official Website: https://madong.tech
 */

namespace app\service\admin\content\review\log;

use app\dao\content\review\ReviewArchiveDao;
use app\dao\content\review\ReviewDao;
use app\dao\content\review\ReviewLogDao;
use core\foundation\base\BaseService;
use Illuminate\Support\Collection;
use support\Container;

/**
 * 审核操作审计日志服务（只读查询）
 */
class ReviewLogService extends BaseService
{
    public function __construct(ReviewLogDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 分页查询操作日志（关联 review / archive 表补充 title/content）
     */
    public function getList(array $where, int $page = 1, int $limit = 20): array
    {
        $list  = $this->dao->selectList($where, '*', $page, $limit, 'id desc');
        $total = $this->dao->getCount($where);

        if (!$list->isEmpty()) {
            $this->attachReviewSnapshot($list);
        }

        return ['list' => $list, 'total' => $total];
    }

    /**
     * 清理指定天数之前的历史日志
     */
    public function cleanBeforeDays(int $days): int
    {
        $before = time() - $days * 86400;
        return $this->dao->getModel()
            ->where('created_at', '>', 0)
            ->where('created_at', '<', $before)
            ->delete();
    }

    /**
     * 从 sys_review 与 sys_review_archive 提取 title/content 快照挂到 log 项
     */
    protected function attachReviewSnapshot(Collection $list): void
    {
        $reviewIds = $list->pluck('review_id')->filter()->unique()->values()->all();
        if (empty($reviewIds)) {
            return;
        }

        /** @var ReviewDao $reviewDao */
        $reviewDao  = Container::make(ReviewDao::class);
        /** @var ReviewArchiveDao $archiveDao */
        $archiveDao = Container::make(ReviewArchiveDao::class);

        $snapshot = [];
        // 运行表
        $reviews = $reviewDao->selectList(['IN_id' => implode(',', $reviewIds)], 'id,extra_data', 0, 0);
        foreach ($reviews ?? [] as $row) {
            $extra = is_array($row->extra_data) ? $row->extra_data : (json_decode((string)$row->extra_data, true) ?: []);
            $snapshot[$row->id] = [
                'title'   => (string)($extra['title'] ?? ''),
                'content' => (string)($extra['content'] ?? ''),
            ];
        }
        // 归档表补缺失
        $missing = array_values(array_diff($reviewIds, array_keys($snapshot)));
        if (!empty($missing)) {
            $archives = $archiveDao->selectList(['IN_id' => implode(',', $missing)], 'id,extra_data', 0, 0);
            foreach ($archives ?? [] as $row) {
                $extra = is_array($row->extra_data) ? $row->extra_data : (json_decode((string)$row->extra_data, true) ?: []);
                $snapshot[$row->id] = [
                    'title'   => (string)($extra['title'] ?? ''),
                    'content' => (string)($extra['content'] ?? ''),
                ];
            }
        }

        foreach ($list as $item) {
            $info = $snapshot[$item->review_id] ?? null;
            $item->title   = $info['title']   ?? '';
            $item->content = $info['content'] ?? '';
        }
    }
}
