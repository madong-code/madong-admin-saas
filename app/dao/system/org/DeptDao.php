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

namespace app\dao\system\org;

use app\model\system\org\Dept;
use core\foundation\base\BaseDao;
use Illuminate\Support\Collection;

class DeptDao extends BaseDao
{

    protected function setModel(): string
    {
        return Dept::class;
    }

    public function selectList(array $where, string|array $field = '*', int $page = 0, int $limit = 0, string $order = '', array $with = [], bool $search = false, ?array $withoutScopes = null): ?\Illuminate\Database\Eloquent\Collection
    {
        return parent::selectList($where, $field, $page, $limit, $order, ['leader'], $search, $withoutScopes);
    }

    /**
     * get
     *
     * @param            $id
     * @param array|null $field
     * @param array|null $with
     * @param string     $order
     * @param array|null $withoutScopes
     *
     * @return \app\model\system\SystemDept|null
     * @throws \Exception
     */
    public function get($id, ?array $field = [], ?array $with = [], string $order = '', ?array $withoutScopes = null): ?Dept
    {
        $model = parent::get($id, ['*'], ['leader'], $order, $withoutScopes);
        if (!empty($model)) {
            $leader = $model->leader;
            $model->set('leader_id_list', []);
            if (!empty($leader)) {
                $model->set('leader_id_list', array_column($leader->toArray() ?? [], 'id'));
            }
        }
        return $model;
    }

    /**
     * 获取部门子级所有id
     *
     * @param string|int $parentId
     * @param true       $includeSelf
     *
     * @return array
     */
    public function getChildIdsIncludingSelf(string|int $parentId, true $includeSelf = true): array
    {
        $allChildIds = new Collection();
        if ($includeSelf) {
            $this->addIdToCollection($parentId, $allChildIds);
        }
        $this->collectChildIds($parentId, $allChildIds);
        return $allChildIds->unique()->values()->all();
    }

    /**
     * 将子级id添加到collection
     *
     * @param                                $id
     * @param \Illuminate\Support\Collection $collection
     */
    private function addIdToCollection($id, Collection &$collection): void
    {
        if (!$collection->contains($id)) {
            $collection->push($id);
        }
    }

    /**
     * 递归获取子级
     *
     * @param                                $parentId
     * @param \Illuminate\Support\Collection $allChildIds
     */
    private function collectChildIds($parentId, Collection &$allChildIds)
    {
        $childIds = Dept::where('pid', $parentId)->pluck('id');
        foreach ($childIds as $childId) {
            $this->addIdToCollection($childId, $allChildIds);
            $this->collectChildIds($childId, $allChildIds);
        }
    }

    /**
     * 获取部门及所有子部门ID（包括自身）
     *
     * @param int $deptId
     *
     * @return array
     */
//    public function getChildIdsIncludingSelf(int $deptId): array
//    {
//        $ids = collect();
//        $this->collectChildIds($deptId, $ids);
//        return $ids->unique()->toArray();
//    }
//
//    /**
//     * 递归收集部门ID
//     *
//     * @param int                            $deptId
//     * @param \Illuminate\Support\Collection $ids
//     */
//    private function collectChildIds(int $deptId, \Illuminate\Support\Collection &$ids): void
//    {
//        $ids->push($deptId);
//        $children = $this->model->where('pid', $deptId)->get();
//        foreach ($children as $child) {
//            $this->collectChildIds($child->id, $ids);
//        }
//    }

}
