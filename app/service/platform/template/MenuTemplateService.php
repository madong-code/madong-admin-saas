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

use app\dao\tenant\MenuTemplateDao;
use app\model\tenant\MenuTemplate;
use app\model\system\menu\Menu;
use app\model\tenant\Tenant;
use core\foundation\base\BaseService;
use madong\helper\Tree;
use support\Log;
use Webman\RedisQueue\Client as RedisClient;

/**
 * 菜单模板服务
 * 操作 saas_template_menu 表，app='admin' 的菜单作为新建租户时的模板
 * 自动同步 sys_menu 的 template_id 关联
 */
class MenuTemplateService extends BaseService
{
    public function __construct(MenuTemplateDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 获取菜单模板树
     */
    public function getTree(string $app = 'admin'): array
    {
        $menus = $this->dao->selectList(['app' => $app, 'enabled' => 1])->toArray();
        $tree = new Tree($menus);
        return $tree->getTree();
    }

    /**
     * 获取扁平列表
     */
    public function getList(string $app = 'admin'): array
    {
        return $this->dao->selectList(['app' => $app])->toArray();
    }

    /**
     * 创建模板菜单（同时创建对应 sys_menu 记录）
     */
    public function create(array $data): MenuTemplate
    {
        $data['app'] = $data['app'] ?? 'admin';

        return $this->dao->getModel()->getConnection()->transaction(function () use ($data) {
            // 1. 创建模板菜单
            $menu = $this->dao->save($data);

            // 2. 同步创建 sys_menu 记录（仅 admin 模板需要）
            if ($menu->app === 'admin') {
                Menu::create([
                    'pid'         => $this->getSysMenuPidByTemplatePid((int)$menu->pid),
                    'template_id' => $menu->id,
                    'app'         => 'admin',
                    'source'      => 'template',
                    'title'       => $menu->title ?? '',
                    'code'        => $menu->code ?? '',
                    'type'        => $menu->type ?? 1,
                    'sort'        => $menu->sort ?? 999,
                    'path'        => $menu->path ?? '',
                    'component'   => $menu->component ?? '',
                    'redirect'    => $menu->redirect ?? '',
                    'icon'        => $menu->icon ?? '',
                    'is_show'     => $menu->is_show ?? 1,
                    'is_link'     => $menu->is_link ?? 0,
                    'link_url'    => $menu->link_url ?? '',
                    'enabled'     => $menu->enabled ?? 1,
                    'open_type'   => $menu->open_type ?? 0,
                    'is_cache'    => $menu->is_cache ?? 0,
                    'is_sync'     => $menu->is_sync ?? 1,
                    'is_affix'    => $menu->is_affix ?? 0,
                    'variable'    => $menu->variable ?? '',
                    'methods'     => $menu->methods ?? 'GET',
                ]);
            }

            return $menu;
        });
    }

    /**
     * 更新模板菜单（同步更新 sys_menu）
     */
    public function update(int $id, array $data): MenuTemplate
    {
        return $this->dao->getModel()->getConnection()->transaction(function () use ($id, $data) {
            $menu = $this->dao->find($id);
            if (!$menu) {
                throw new \RuntimeException('菜单不存在');
            }
            $menu->fill($data);
            $menu->save();

            // 同步更新所有关联的 sys_menu（通过 template_id）
            $this->syncToFieldTenants($menu);

            // database 模式：通过队列异步处理
            $this->pushTenantMenuUpdateTask($id, $data);

            return $menu;
        });
    }

    /**
     * 删除模板菜单（含子菜单，同步删除 sys_menu，并通知各租户）
     */
    public function delete(int $id): void
    {
        $childIds = $this->getAllChildIds($id);
        $ids = array_merge([$id], $childIds);

        $this->dao->getModel()->getConnection()->transaction(function () use ($ids) {
            // 1. 删除所有关联的 sys_menu 记录（通过 template_id）
            Menu::whereIn('template_id', $ids)->delete();

            // 2. 删除模板菜单
            $this->dao->delete($ids);
        });

        // 4. field 模式租户：直接删除租户菜单
        $this->syncDeleteToFieldTenants($ids);

        // 5. database 模式租户：队列异步
        $this->pushTenantMenuDeleteTask($ids);
    }

    /**
     * 批量删除模板菜单
     */
    public function batchDelete(array $ids): void
    {
        $allIds = [];
        foreach ($ids as $id) {
            $childIds = $this->getAllChildIds((int)$id);
            $allIds = array_merge($allIds, [$id], $childIds);
        }
        $allIds = array_unique($allIds);

        $this->dao->getModel()->getConnection()->transaction(function () use ($allIds) {
            Menu::whereIn('template_id', $allIds)->delete();
            $this->dao->delete($allIds);
        });

        $this->syncDeleteToFieldTenants($allIds);
        $this->pushTenantMenuDeleteTask($allIds);
    }


    // ==================== 租户同步（field 模式直接执行） ====================

    /**
     * field 模式租户：同步更新菜单
     */
    private function syncToFieldTenants(MenuTemplate $template): void
    {
        try {
            Menu::where('template_id', $template->id)->update($template->toArray());
        } catch (\Throwable $e) {
            Log::error('同步菜单到field模式租户失败', [
                'template_id' => $template->id,
                'error'       => $e->getMessage(),
            ]);
        }
    }

    /**
     * field 模式租户：同步删除菜单（含子菜单）
     */
    private function syncDeleteToFieldTenants(array $templateIds): void
    {
        try {
            Menu::whereIn('template_id', $templateIds)->delete();
        } catch (\Throwable $e) {
            Log::error('同步删除field模式租户菜单失败', [
                'template_ids' => $templateIds,
                'error'        => $e->getMessage(),
            ]);
        }
    }

    // ==================== 队列任务（database 模式） ====================

    /**
     * 推送菜单更新任务到队列（database 模式租户）
     */
    private function pushTenantMenuUpdateTask(int $templateId, array $data): void
    {
        try {
            $tenantIds = $this->getDatabaseModeTenantIds();
            if (!empty($tenantIds)) {
                RedisClient::send('tenant-menu-sync', [
                    'action'     => 'update',
                    'template_id' => $templateId,
                    'data'       => $data,
                    'tenant_ids' => $tenantIds,
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('推送菜单更新队列失败', ['error' => $e->getMessage()]);
        }
    }

    /**
     * 推送菜单删除任务到队列（database 模式租户）
     */
    private function pushTenantMenuDeleteTask(array $templateIds): void
    {
        try {
            $tenantIds = $this->getDatabaseModeTenantIds();
            if (!empty($tenantIds)) {
                RedisClient::send('tenant-menu-sync', [
                    'action'       => 'delete',
                    'template_ids' => $templateIds,
                    'tenant_ids'   => $tenantIds,
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('推送菜单删除队列失败', ['error' => $e->getMessage()]);
        }
    }

    /**
     * 获取所有 database 模式的租户ID
     */
    private function getDatabaseModeTenantIds(): array
    {
        return Tenant::where('database_mode', 'database')
            ->where('status', 'active')
            ->pluck('id')
            ->toArray();
    }

    // ==================== 辅助方法 ====================

    /**
     * 根据模板 pid 查找对应的 sys_menu pid
     */
    private function getSysMenuPidByTemplatePid(int $templatePid): int
    {
        if ($templatePid === 0) return 0;
        $parentSys = Menu::where('template_id', $templatePid)->first();
        return $parentSys ? (int)$parentSys->id : 0;
    }

    /**
     * 获取所有子菜单ID
     */
    private function getAllChildIds(int $parentId): array
    {
        $ids = [];
        $children = $this->dao->getColumn(['pid' => $parentId], 'id');
        foreach ($children as $childId) {
            $ids[] = $childId;
            $ids = array_merge($ids, $this->getAllChildIds($childId));
        }
        return $ids;
    }
}
