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

namespace app\service\platform\template;

use app\dao\tenant\WebMenuTemplateDao;
use app\model\tenant\WebMenuTemplate;
use core\foundation\base\BaseService;
use madong\helper\Tree;

/**
 * 前台菜单模板服务
 *
 * 操作 saas_template_web_menu 表，由平台运营人员配置前台菜单模板。
 * FIELD/DB 模式新建租户时复制到租户的 web_menu 表。
 */
class WebMenuTemplateService extends BaseService
{
    public function __construct(WebMenuTemplateDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 获取前台菜单模板列表（分页，给CRUD组件使用）
     */
    public function selectList(array $where, string|array $field = '*', int $page = 0, int $limit = 0, string $order = '', array $with = [], bool $search = false, ?array $withoutScopes = null): ?\Illuminate\Database\Eloquent\Collection
    {
        if (empty($order)) {
            $order = 'sort asc, id asc';
        }
        return $this->dao->selectList($where, $field, $page, $limit, $order, $with, $search, $withoutScopes);
    }

    /**
     * 获取记录数
     */
    public function getCount(array $where): int
    {
        return $this->dao->getCount($where);
    }

    /**
     * 获取前台菜单模板树
     */
    public function getTree(): array
    {
        $menus = $this->dao->selectList([])->toArray();
        $tree = new Tree($menus);
        return $tree->getTree();
    }

    /**
     * 获取前台菜单列表（扁平列表）
     */
    public function getList(array $where = []): array
    {
        return $this->dao->selectList($where)->toArray();
    }

    /**
     * 创建前台菜单模板
     */
    public function create(array $data): WebMenuTemplate
    {
        return $this->dao->save($data);
    }

    /**
     * 更新前台菜单模板
     */
    public function update(int $id, array $data): WebMenuTemplate
    {
        $this->dao->update($id, $data);
        return $this->dao->find($id);
    }

    /**
     * 删除前台菜单模板
     */
    public function delete(int $id): void
    {
        $this->dao->delete($id);
    }

    /**
     * 批量删除前台菜单模板
     */
    public function batchDelete(array $ids): void
    {
        $this->dao->delete($ids);
    }
}
