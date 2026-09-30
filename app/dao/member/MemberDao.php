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
namespace app\dao\member;

use app\scope\global\AccessPermissionScope;
use app\service\admin\member\MemberTagRelationService;
use app\service\admin\member\MemberTagService;
use core\foundation\base\BaseDao;
use app\model\member\Member;
use madong\query\QueryBuilderHelper;
use madong\query\QueryParamConverter;
use madong\query\enum\ParamFormat;
use InvalidArgumentException;
use support\Container;

/**
 * 会员DAO
 */
class MemberDao extends BaseDao
{

    /**
     * 设置模型
     */
    protected function setModel(): string
    {
        return Member::class;
    }

    /**
     * 获取活跃用户
     *
     * @param int $limit
     *
     * @return \Illuminate\Database\Eloquent\Collection
     * @throws \Exception
     */
    public function getActiveUsers(int $limit = 5): \Illuminate\Database\Eloquent\Collection
    {
        // last_time 字段存储为时间戳格式（dateFormat = 'U'）
        // 计算30天前的时间戳
        $thirtyDaysAgo = time() - (30 * 24 * 60 * 60);

        return $this->query()->withoutGlobalScope(AccessPermissionScope::class)
            ->where('last_login_time', '>=', $thirtyDaysAgo)
            ->orderBy('last_login_time', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * 根据标签ID获取用户列表
     *
     * @param array      $where         查询条件，支持多种格式自动转换
     * @param string     $field         查询字段
     * @param int        $page          页码
     * @param int        $limit         每页数量
     * @param array|null $withoutScopes 移除的作用域
     *
     * @return array ['total' => int, 'items' => array]
     * @throws \Exception
     */
    public function getUsersListByTagId(array $where, string $field, int $page, int $limit, ?array $withoutScopes = null): array
    {
        try {
            //传入的是标准filters格式
            $tagId = QueryParamConverter::getValue($where, 'tag_id');
            if (!$tagId) {
                throw new InvalidArgumentException("Tag ID is required.");
            }
            // 从 where 中移除 tag_id
            $where = QueryParamConverter::removeFilter($where, 'tag_id');

            $query = $this->query()->with(['tags' => function ($query) use ($withoutScopes) {
                $this->applyScopeRemoval($query, $withoutScopes);
            }])
                ->whereHas('tags', function ($query) use ($tagId) {
                    $query->where('id', $tagId);
                });

            $this->applyScopeRemoval($query, $withoutScopes);

            // 应用剩余的 filters 条件
            if (!empty($where['filters'])) {
                QueryBuilderHelper::applyFiltersToQuery($query, $where['filters']);
            }

            $total = $query->count();

            $items = $query->when($page > 0 && $limit > 0, function ($query) use ($page, $limit) {
                return $query->skip(($page - 1) * $limit)->take($limit);
            })
                ->select(explode(',', $field))
                ->get()
                ->toArray();

            return compact('total', 'items');
        } catch (\Throwable $e) {
            throw new \Exception("获取用户列表失败: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * 排除标签ID-用户列表
     *
     * @param array      $where
     * @param string     $field
     * @param int        $page
     * @param int        $limit
     * @param array|null $withoutScopes
     *
     * @return array
     * @throws \ReflectionException|\Exception
     */
    public function getUsersExcludingTag(array $where, string $field, int $page, int $limit, ?array $withoutScopes = null): array
    {

        // 使用 QueryParamConverter 获取 tag_id 值（自动识别格式）
        $tagId = QueryParamConverter::getValue($where, 'tag_id');

        // 从 where 中移除 tag_id
        $where = QueryParamConverter::removeFilter($where, 'tag_id');
        // 获取排除的用户ID列表
        if ($tagId) {
            $memberTagRelationService = Container::make(MemberTagRelationService::class);

            $excludedMemberUserIds = $memberTagRelationService->getColumn(['tag_id' => $tagId], 'member_id');
        } else {
            $excludedMemberUserIds = [];
        }

        $query = $this->getModel()->whereNotIn('id', $excludedMemberUserIds)
            ->with(['tags' => function ($query) use ($withoutScopes) {
                $this->applyScopeRemoval($query, $withoutScopes);
            }]);
        $this->applyScopeRemoval($query, $withoutScopes);

        // 应用剩余的 filters 条件
        if (!empty($where['filters'])) {
            QueryBuilderHelper::applyFiltersToQuery($query, $where['filters']);
        }

        $total = $query->count();

        // 如果没有用户，则返回空结果
        if ($total === 0) {
            return ['total' => 0, 'items' => []];
        }

        $items = $query->when($page > 0 && $limit > 0, function ($query) use ($page, $limit) {
            return $query->skip(($page - 1) * $limit)->take($limit);
        })->select($field)->get()->toArray();

        return compact('total', 'items');
    }

}


