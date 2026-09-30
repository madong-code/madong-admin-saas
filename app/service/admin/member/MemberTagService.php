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
use app\dao\member\MemberTagDao;
use app\dao\member\MemberTagRelationDao;
use app\model\web\Menu;
use app\model\member\MemberTag;
use core\foundation\base\BaseService;
use core\foundation\exception\handler\AdminException;
use support\Container;

/**
 * 会员标签服务类
 */
class MemberTagService extends BaseService
{

    /**
     * 构造方法
     */
    public function __construct(MemberTagDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 为会员添加标签
     */
    public function addTagToMember(int $memberId, int $tagId): bool
    {
        /** @var MemberTagRelationDao $dao */
        $dao = Container::make(MemberTagRelationDao::class);
        return $dao->addTagRelation($memberId, $tagId);
    }

    /**
     * 为会员移除标签
     */
    public function removeTagFromMember(int $memberId, int $tagId): bool
    {
        /** @var MemberTagRelationDao $dao */
        $dao = Container::make(MemberTagRelationDao::class);
        return $dao->removeTagRelation($memberId, $tagId);
    }

    /**
     * 获取标签下的会员列表
     */
    public function getTagMembers(int $tagId, array $params): array
    {
        $tag = $this->dao->get($tagId);
        if (!$tag) {
            throw new AdminException('标签不存在');
        }

        $page  = (int)($params['page'] ?? 1);
        $limit = (int)($params['limit'] ?? 10);

        /** @var MemberTagRelationDao $relationDao */
        $relationDao = Container::make(MemberTagRelationDao::class);
        /** @var MemberDao $memberDao */
        $memberDao = Container::make(MemberDao::class);

        // 获取标签关联的会员ID
        $relations = $relationDao->selectList(
            [['tag_id', '=', $tagId]],
            'member_id',
            0, 10000
        );
        $memberIds = $relations->pluck('member_id')->toArray();

        if (empty($memberIds)) {
            return ['total' => 0, 'list' => []];
        }

        $where = [['id', 'in', $memberIds]];
        $field = '*';
        $order = 'create_time desc';

        [$total, $list] = $memberDao->getList($where, $field, $page, $limit, $order);

        return compact('total', 'list');
    }

    /**
     * 批量分配标签
     */
    public function batchAssignTags(array $data): array
    {
        $memberIds = $data['member_ids'] ?? [];
        $tagIds    = $data['tag_ids'] ?? [];
        $action    = $data['action'] ?? 'assign';

        if (empty($memberIds)) {
            throw new AdminException('请选择会员');
        }

        if (empty($tagIds)) {
            throw new AdminException('请选择标签');
        }

        /** @var MemberTagRelationDao $relationDao */
        $relationDao = Container::make(MemberTagRelationDao::class);

        $relationDao->getModel()->getConnection()->beginTransaction();
        try {
            foreach ($memberIds as $memberId) {
                if ($action == 'assign') {
                    foreach ($tagIds as $tagId) {
                        $this->addTagToMember($memberId, $tagId);
                    }
                } else if ($action == 'remove') {
                    foreach ($tagIds as $tagId) {
                        $this->removeTagFromMember($memberId, $tagId);
                    }
                }
            }

            $relationDao->getModel()->getConnection()->commit();
            return ['success' => true, 'count' => count($memberIds)];
        } catch (\Exception $e) {
            $relationDao->getModel()->getConnection()->rollBack();
            throw $e;
        }
    }

    /**
     * 获取所有启用的标签
     */
    public function getEnabledTags(): array
    {
        $where = [['status', '=', 1]];
        $list  = $this->dao->selectList($where, '*', 0, 1000, 'sort asc');
        return $list->toArray();
    }

    /**
     * 更新标签排序
     */
    public function updateSort(int $id, int $sort): array
    {
        $tag = $this->dao->get($id);
        if (!$tag) {
            throw new AdminException('标签不存在');
        }

        $tag->sort = $sort;
        $tag->save();

        return $tag->toArray();
    }

    /**
     * 为标签分配权限
     */
    public function assignPermissions(int $tagId, array $permissionCodes): bool
    {
        // 验证标签存在
        $tag = $this->dao->get($tagId);
        if (!$tag) {
            throw new AdminException('标签不存在');
        }

        // 验证权限代码是否存在于 web_menu 表中（code 不为空）
        if (!empty($permissionCodes)) {
            $validCodes = Menu::whereIn('code', $permissionCodes)
                ->whereNotNull('code')
                ->where('code', '<>', '')
                ->where('enabled', 1)
                ->pluck('code')
                ->toArray();

            if (count($validCodes) !== count($permissionCodes)) {
                $invalidCodes = array_diff($permissionCodes, $validCodes);
                throw new AdminException('以下权限码不存在或已禁用: ' . implode(', ', $invalidCodes));
            }
        }

        // 使用 belongsToMany 同步权限（标准 pivot 操作）
        $tagModel = MemberTag::find($tagId);
        if (!$tagModel) {
            throw new AdminException('标签不存在');
        }

        $tagModel->permissions()->sync($validCodes);

        return true;
    }

    /**
     * 获取会员的所有权限
     */
    public function getMemberPermissions(int $memberId): array
    {
        $member = MemberDao::make()->get($memberId);
        if (!$member) {
            return [];
        }

        // 查询会员的所有启用标签及权限
        $tags = MemberTag::with(['permissions'])
            ->whereHas('members', function ($query) use ($memberId) {
                $query->where('member_id', $memberId);
            })
            ->where('enabled', 1)
            ->get();

        if ($tags->isEmpty()) {
            return [];
        }

        // 收集所有权限
        $permissions = [];
        foreach ($tags as $tag) {
            foreach ($tag->permissions as $permission) {
                $permissions[] = $permission->code;
            }
        }

        return array_unique($permissions);
    }

    /**
     * 获取所有启用的标签（含权限）
     */
    public function getEnabledTagsWithPermissions(): array
    {
        return MemberTag::with('permissions')
            ->where('enabled', 1)
            ->orderBy('sort')
            ->get()
            ->toArray();
    }

    /**
     * 获取未包含在指定标签中的会员列表
     */
    public function getExcludedMembers(int $tagId, array $params): array
    {
        $tag = $this->dao->get($tagId);
        if (!$tag) {
            throw new AdminException('标签不存在');
        }

        $page  = (int)($params['page'] ?? 1);
        $limit = (int)($params['limit'] ?? 10);
        $username = $params['username'] ?? '';
        $nickname = $params['nickname'] ?? '';

        /** @var MemberTagRelationDao $relationDao */
        $relationDao = Container::make(MemberTagRelationDao::class);

        // 获取标签关联的会员ID
        $relations = $relationDao->selectList(
            [['tag_id', '=', $tagId]],
            'member_id',
            0, 10000
        );
        $memberIds = $relations->pluck('member_id')->toArray();

        /** @var MemberDao $memberDao */
        $memberDao = Container::make(MemberDao::class);

        // 构建排除条件
        $where = [];
        if (!empty($memberIds)) {
            $where[] = ['id', 'not in', $memberIds];
        }
        if (!empty($username)) {
            $where[] = ['username', 'like', "%{$username}%"];
        }
        if (!empty($nickname)) {
            $where[] = ['nickname', 'like', "%{$nickname}%"];
        }

        $field = '*';
        $order = 'create_time desc';

        [$total, $list] = $memberDao->getList($where, $field, $page, $limit, $order);

        return compact('total', 'list');
    }

    /**
     * 为标签保存菜单权限（通过菜单ID）
     */
    public function saveTagMenuIds(int $tagId, array $menuIds): bool
    {
        $tag = $this->dao->get($tagId);
        if (!$tag) {
            throw new AdminException('标签不存在');
        }

        // 验证菜单ID是否存在
        if (!empty($menuIds)) {
            $validIds = Menu::whereIn('id', $menuIds)->pluck('id')->toArray();
            if (count($validIds) !== count($menuIds)) {
                $invalidIds = array_diff($menuIds, $validIds);
                throw new AdminException('以下菜单ID不存在: ' . implode(', ', $invalidIds));
            }
        }

        $tagModel = MemberTag::find($tagId);
        if (!$tagModel) {
            throw new AdminException('标签不存在');
        }

        // 使用 belongsToMany 同步菜单ID
        $tagModel->permissions()->sync($menuIds);

        return true;
    }

    /**
     * 获取所有可用的权限码（从 web_menu 表）
     */
    public function getAvailablePermissions(): array
    {
        return Menu::whereNotNull('code')
            ->where('code', '<>', '')
            ->where('enabled', 1)
            ->orderBy('sort')
            ->get(['id', 'code', 'name', 'url', 'category'])
            ->toArray();
    }

}