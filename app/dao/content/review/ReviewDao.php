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

use app\enum\review\ReviewStatus;
use app\model\content\review\Review;
use core\foundation\base\BaseDao;

/**
 * 审核DAO（运行表）
 *
 * 审核状态流转、归档、业务回调等统一由 ReviewService 编排，
 * DAO 仅负责数据读写与 extra_data JSON 字段搜索。
 */
class ReviewDao extends BaseDao
{
    protected function setModel(): string
    {
        return Review::class;
    }

    /**
     * 待审核数量
     */
    public function getPendingCount(?string $reviewableType = null): int
    {
        $query = $this->getModel()->query()->where('status', ReviewStatus::PENDING->value);
        if ($reviewableType) {
            $query->where('reviewable_type', $reviewableType);
        }
        return $query->count();
    }

    /**
     * 根据关联对象获取审核记录
     */
    public function getByReviewable(string $reviewableType, int|string $reviewableId): ?\Illuminate\Database\Eloquent\Model
    {
        return $this->getOne([
            'reviewable_type' => $reviewableType,
            'reviewable_id'   => $reviewableId,
        ]);
    }

    /**
     * 列表（支持 extra_data 字段搜索）
     *
     * @param array $where
     * @param string|array $field
     * @param int $page
     * @param int $limit
     * @param string $order
     * @param array $with
     * @param bool $search
     * @param array|null $withoutScopes
     * @return \Illuminate\Database\Eloquent\Collection|null
     * @throws \Exception
     */
    public function selectList(array $where, string|array $field = '*', int $page = 0, int $limit = 0, string $order = '', array $with = [], bool $search = false, ?array $withoutScopes = null): ?\Illuminate\Database\Eloquent\Collection
    {
        $query = $this->getModel()->query();

        if (!empty($withoutScopes)) {
            $this->applyScopeRemoval($query, $withoutScopes);
        }

        $this->applyExtraDataSearch($query, $where);

        $normalWhere = $this->filterExtraDataConditions($where);
        if (!empty($normalWhere)) {
            $options = ['keyword_fields' => $this->getKeywordFields()];
            if (!$search) {
                $options['scopes'] = [];
            }
            $query = $this->applyQueryParams($query, $normalWhere, $options);
        }

        $isWildcard = ($field === '*' || ($field === ['*']));
        if (!$isWildcard) {
            if (is_array($field)) {
                $field = implode(',', $field);
            }
            $query->selectRaw($field);
        }

        if ($order !== '') {
            $query->orderByRaw($order);
        }

        if (!empty($with)) {
            $query->with($with);
        }

        if ($page > 0 && $limit > 0) {
            return $query->paginate($limit, ['*'], 'page', $page)->getCollection();
        }
        return $query->get();
    }

    /**
     * 数量（支持 extra_data 字段搜索）
     */
    public function count(array $where = [], bool $search = false): int
    {
        $query = $this->getModel()->query();

        $this->applyExtraDataSearch($query, $where);

        $normalWhere = $this->filterExtraDataConditions($where);
        if (!empty($normalWhere)) {
            $options = ['keyword_fields' => $this->getKeywordFields()];
            if (!$search) {
                $options['scopes'] = [];
            }
            $query = $this->applyQueryParams($query, $normalWhere, $options);
        }

        return $query->count();
    }

    protected function applyExtraDataSearch(\Illuminate\Database\Eloquent\Builder $query, array $where): void
    {
        $conditions = [];
        foreach ($where as $key => $value) {
            if (is_string($key) && preg_match('/^LIKE_(title|applicant|content)$/i', $key, $matches)) {
                $conditions[] = ['field' => strtolower($matches[1]), 'value' => $value];
            }
        }
        if (!empty($conditions)) {
            $query->where(function (\Illuminate\Database\Eloquent\Builder $subQuery) use ($conditions) {
                foreach ($conditions as $condition) {
                    $subQuery->orWhereRaw(
                        "JSON_EXTRACT(extra_data, ?) LIKE ?",
                        ["\$.{$condition['field']}", "%{$condition['value']}%"]
                    );
                }
            });
        }
    }

    protected function filterExtraDataConditions(array $where): array
    {
        $filtered = [];
        foreach ($where as $key => $value) {
            if (!is_string($key) || !preg_match('/^LIKE_(title|applicant|content)$/i', $key)) {
                $filtered[$key] = $value;
            }
        }
        return $filtered;
    }
}
