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
namespace app\service\platform\menu;

use app\dao\tenant\PlatformMenuDao;
use app\model\tenant\PlatformMenu;
use core\foundation\base\BaseService;
use madong\helper\Tree;

/**
 * 平台运营端菜单服务
 * 操作 saas_template_menu 表，app='platform' 区分
 */
class PlatformMenuService extends BaseService
{
    public function __construct(PlatformMenuDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 获取菜单树
     */
    public function getTree(): array
    {
        $menus = $this->dao->selectList(['app' => 'platform', 'enabled' => 1])->toArray();

        $tree = new Tree($menus);
        return $tree->getTree();
    }

    /**
     * 获取扁平列表
     *
     * @param array $where Crud selectInput 解析出的查询条件
     */
    public function getList(array $where = []): array
    {
        // Crud 流程默认 filter_format=keyvalue（field => 'op:value'），追加条件需保持同一格式
        $where['filters'] = array_merge($where['filters'] ?? [], ['app' => 'eq:platform']);
        return $this->dao->selectList($where)->toArray();
    }

    /**
     * 创建菜单
     */
    public function create(array $data): PlatformMenu
    {
        $data['app'] = $data['app'] ?? 'platform';
        return $this->dao->save($data);
    }

    /**
     * 更新菜单
     */
    public function update(int $id, array $data): PlatformMenu
    {
        $menu = $this->dao->find($id);
        if (!$menu) {
            throw new \RuntimeException('菜单不存在');
        }
        $menu->fill($data);
        $menu->save();
        return $menu;
    }

    /**
     * 删除菜单（含子菜单）
     */
    public function delete(int $id): void
    {
        $childIds = $this->getAllChildIds($id);
        $ids = array_merge([$id], $childIds);
        $this->dao->delete($ids);
    }

    /**
     * 获取所有子菜单ID
     */
    private function getAllChildIds(int|string $parentId): array
    {
        $parentId = (int) $parentId;
        $ids      = [];
        $children = $this->dao->getColumn(['pid' => $parentId], 'id');
        foreach ($children as $childId) {
            $ids[] = (int) $childId;
            $ids   = array_merge($ids, $this->getAllChildIds($childId));
        }
        return $ids;
    }
}
