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

namespace app\dao\content\review;

use app\model\content\review\ReviewArchive;
use core\foundation\base\BaseDao;
use Illuminate\Database\Eloquent\Collection;

/**
 * 审核归档DAO（冷数据表）
 */
class ReviewArchiveDao extends BaseDao
{
    protected function setModel(): string
    {
        return ReviewArchive::class;
    }

    /**
     * 查询已终结归档列表（archived_at 非空），排除运行期写入、仍运行中的记录
     */
    public function selectFinishedList(array $where, $field = '*', int $page = 0, int $limit = 0, string $order = '', array $with = [], bool $search = false): ?Collection
    {
        $query = $this->selectModel($where, $field, $page, $limit, $order, $with, $search);
        $query->whereNotNull('archived_at');
        if ($page > 0 && $limit > 0) {
            return $query->paginate($limit, ['*'], 'page', $page)->getCollection();
        }
        return $query->get();
    }

    /**
     * 统计已终结归档数量（archived_at 非空）
     */
    public function countFinished(array $where = []): int
    {
        $query = $this->getModel()->query();
        if (!empty($where)) {
            $query = $this->applyQueryParams($query, $where, $this->getQueryOptions(true));
        }
        return $query->whereNotNull('archived_at')->count();
    }
}
