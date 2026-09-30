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
namespace app\service\admin\content\notepad;

use app\dao\content\notepad\NotepadFolderDao;
use core\business\tenant\context\TenantContext;
use core\foundation\base\BaseService;
use Illuminate\Database\Eloquent\Builder;

class NotepadFolderService extends BaseService
{
    public function __construct(NotepadFolderDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 平台端无租户上下文时强制只操作 tenant_id 为空的数据，
     * 避免平台端混入租户记事本数据。
     */
    protected function tenantFilter(Builder $query): Builder
    {
        if (TenantContext::getTenantId() === null) {
            $query->where(function ($q) {
                $q->whereNull('tenant_id')
                  ->orWhere('tenant_id', '')
                  ->orWhere('tenant_id', '0');
            });
        }
        return $query;
    }

    /**
     * 获取文件夹树
     */
    public function getTree(string $userId): array
    {
        if ($userId === '' || $userId === '0') {
            return [];
        }

        $query = $this->dao->getModel()::query()->where('user_id', $userId);
        $this->tenantFilter($query);
        $list = $query->orderBy('sort', 'asc')->get()->toArray();

        foreach ($list as &$item) {
            $item['doc_count'] = (int) ($item['doc_count'] ?? 0);
        }

        return $this->buildTree($list, '0');
    }

    /**
     * 获取所有文件夹（扁平列表）
     */
    public function getList(string $userId): array
    {
        if ($userId === '' || $userId === '0') {
            return [];
        }

        $query = $this->dao->getModel()::query()->where('user_id', $userId);
        $this->tenantFilter($query);
        return $query->orderBy('sort', 'asc')->get()->toArray();
    }

    /**
     * 创建文件夹
     */
    public function createByUser(string $userId, array $data): \app\model\content\notepad\Folder
    {
        if ($userId === '' || $userId === '0') {
            throw new \RuntimeException('用户未登录，无法创建文件夹');
        }

        $pid = $data['pid'] ?? '0';
        $sort = (int) ($data['sort'] ?? 0);

        $model = $this->dao->getModel();
        if ($sort === 0) {
            $maxQuery = $model::query()->where('user_id', $userId);
            $this->tenantFilter($maxQuery);
            $sort = (int) $maxQuery->max('sort') + 1;
        }

        return $model::query()->create([
            'pid'       => $pid,
            'user_id'   => $userId,
            'name'      => trim((string) $data['name']),
            'icon'      => $data['icon'] ?? null,
            'sort'      => $sort,
            'doc_count' => 0,
        ]);
    }

    /**
     * 更新文件夹
     *
     * @return bool true 成功 false 文件夹不存在
     */
    public function updateByUser(string $userId, string $id, array $data): bool
    {
        if ($userId === '' || $userId === '0') {
            return false;
        }

        $folderQuery = $this->dao->getModel()::query()
            ->where('id', $id)
            ->where('user_id', $userId);
        $this->tenantFilter($folderQuery);
        $folder = $folderQuery->first();

        if (!$folder) {
            return false;
        }

        if (isset($data['name'])) {
            $folder->name = trim((string) $data['name']);
        }
        if (isset($data['icon'])) {
            $folder->icon = $data['icon'];
        }
        if (isset($data['sort'])) {
            $folder->sort = (int) $data['sort'];
        }
        $folder->save();

        return true;
    }

    /**
     * 删除文件夹（含子文件夹和文档）
     *
     * @param NotepadDocumentService $documentService 用于级联删除文档
     *
     * @return bool true 成功 false 文件夹不存在
     */
    public function deleteByUser(string $userId, string $id, NotepadDocumentService $documentService): bool
    {
        if ($userId === '' || $userId === '0') {
            return false;
        }

        $model = $this->dao->getModel();

        $folderQuery = $model::query()
            ->where('id', $id)
            ->where('user_id', $userId);
        $this->tenantFilter($folderQuery);
        $folder = $folderQuery->first();

        if (!$folder) {
            return false;
        }

        $idsToDelete = $this->collectFolderIds($userId, $id);
        $idsToDelete[] = $id;

        // 删除所有文档
        $documentService->deleteByFolderIds($userId, $idsToDelete);

        // 删除文件夹
        $deleteQuery = $model::query()
            ->whereIn('id', $idsToDelete)
            ->where('user_id', $userId);
        $this->tenantFilter($deleteQuery);
        $deleteQuery->delete();

        return true;
    }

    /**
     * 递归收集子文件夹ID
     */
    private function collectFolderIds(string $userId, string $id): array
    {
        $ids = [];
        $childrenQuery = $this->dao->getModel()::query()
            ->where('pid', $id)
            ->where('user_id', $userId);
        $this->tenantFilter($childrenQuery);
        $children = $childrenQuery->get();

        foreach ($children as $child) {
            $ids[] = $child->id;
            $ids = array_merge($ids, $this->collectFolderIds($userId, $child->id));
        }

        return $ids;
    }

    /**
     * 构建树形结构
     */
    private function buildTree(array $list, string $pid): array
    {
        $tree = [];
        foreach ($list as $item) {
            if ((string) ($item['pid'] ?? '0') === $pid) {
                $children = $this->buildTree($list, (string) $item['id']);
                $node = $item;
                if (!empty($children)) {
                    $node['children'] = $children;
                }
                $tree[] = $node;
            }
        }
        usort($tree, static fn ($a, $b) => ($a['sort'] ?? 0) <=> ($b['sort'] ?? 0));
        return $tree;
    }
}
