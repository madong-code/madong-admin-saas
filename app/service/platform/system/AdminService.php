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

namespace app\service\platform\system;

use app\dao\system\admin\AdminDao;
use app\model\system\admin\Admin;
use app\service\admin\system\AdminRoleService;
use core\foundation\base\BaseService;
use core\foundation\exception\handler\AdminException;
use core\business\tenant\scope\TenantScope;
use Illuminate\Database\Eloquent\Model;
use support\Container;

/**
 * 平台端用户管理 Service
 * 
 * 与 adminapi 的 AdminService 共享 AdminDao/AdminModel，
 * 但查询时明确绕过 TenantScope，确保平台可查看所有租户用户。
 */
class AdminService extends BaseService
{
    public function __construct(AdminDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 用户列表（平台端：查看全部租户用户）
     */
    public function getList(array $where, string $field = '*', int $page = 0, int $limit = 0, string $order = '', array $with = [], bool $search = false): array
    {
        // 使用 AdminDao 的 getList 并传递 withoutScopes=[TenantScope::class]
        // 注意：getList 内部还有 $tenantId = TenantContext::getTenantId() 的手动过滤，
        // 但由于 platform 端 Token 中间件不设置 TenantContext，getTenantId() 返回 null，手动过滤不生效
        return $this->dao->getList($where, $field, $page, $limit, $order, $with, $search, [TenantScope::class]);
    }

    /**
     * 用户详情（平台端）
     */
    public function getAdminById(string|int $id): ?Admin
    {
        return $this->dao->getAdminById($id, [TenantScope::class]);
    }

    /**
     * 新增用户（平台端）
     */
    public function save(array $data): Admin|null
    {
        try {
            return $this->transaction(function () use ($data) {
                $data['password'] = password_hash($data['password'], PASSWORD_DEFAULT);
                $roles            = $data['role_id_list'] ?? [];
                $posts            = $data['post_id_list'] ?? [];
                $depts            = array_filter(explode(',', $data['dept_id_list'] ?? ''));
                $mainDeptId       = $data['main_dept_id'] ?? null;
                $mainPosId        = $data['main_post_id'] ?? null;
                unset($data['role_id_list'], $data['post_id_list'], $data['dept_id_list'], $data['main_dept_id'], $data['main_post_id']);

                // 平台端：不自动填充 tenant_id（创建平台级用户）
                $model = $this->dao->save($data);

                $this->updateModel($model, $data, $depts, $posts);
                $this->syncRoles($model, $roles);
                $this->syncMainInfo($model, $mainDeptId, $mainPosId);
                return $model;
            });
        } catch (\Throwable $e) {
            throw new AdminException($e->getMessage());
        }
    }

    /**
     * 更新用户（平台端）
     */
    public function update(int|string $id, array $data): ?Admin
    {
        try {
            return $this->transaction(function () use ($id, $data) {
                $this->updatePasswordIfNeeded($data);
                $roles      = $data['role_id_list'] ?? [];
                $posts      = $data['post_id_list'] ?? [];
                $depts      = $data['dept_id_list'] ?? [];
                $mainDeptId = $data['main_dept_id'] ?? null;
                $mainPosId  = $data['main_post_id'] ?? null;
                unset($data['role_id_list'], $data['post_id_list'], $data['dept_id_list'], $data['main_dept_id'], $data['main_post_id']);

                // 平台端：绕过 TenantScope，按 id 查找任意租户用户
                $model = $this->dao->getModel()
                    ->withoutGlobalScope(TenantScope::class)
                    ->findOrFail($id);

                $this->updateModel($model, $data, $depts, $posts);
                $this->syncRoles($model, $roles);
                $this->syncMainInfo($model, $mainDeptId, $mainPosId);
                return $model;
            });
        } catch (\Throwable $e) {
            throw new AdminException($e->getMessage());
        }
    }

    /**
     * 删除用户（平台端）
     */
    public function batchDelete(array $ids): array
    {
        try {
            return $this->transaction(function () use ($ids) {
                // 验证：禁止删除超级管理员
                $superUserCount = $this->dao->getModel()
                    ->withoutGlobalScope(TenantScope::class)
                    ->whereIn('id', $ids)
                    ->where('is_super', 1)
                    ->count();
                if ($superUserCount > 0) {
                    throw new AdminException('系统内置用户不允许删除');
                }

                $admins = $this->dao->getModel()
                    ->withoutGlobalScope(TenantScope::class)
                    ->whereIn('id', $ids)
                    ->get();
                foreach ($admins as $admin) {
                    $admin->roles()->detach();
                    $admin->depts()->detach();
                }

                $deleteCount = $this->dao->destroy($ids);
                if ($deleteCount <= 0) {
                    throw new AdminException('删除失败，未找到有效用户');
                }
                return ['id' => $ids];
            });
        } catch (\Throwable $e) {
            throw new AdminException($e->getMessage());
        }
    }

    /**
     * 冻结用户（平台端）
     */
    public function locked(array|string $id): void
    {
        try {
            if (is_string($id)) {
                $id = array_map('trim', explode(',', $id));
            }
            // 绕过 TenantScope 检查所有租户的 super admin
            $ret = $this->dao->getModel()
                ->withoutGlobalScope(TenantScope::class)
                ->whereIn('id', $id)
                ->where('is_super', 1)
                ->count();
            if ($ret > 0) {
                throw new AdminException('系统内置用户，不允许冻结');
            }
            $this->dao->getModel()
                ->withoutGlobalScope(TenantScope::class)
                ->whereIn('id', $id)
                ->update(['is_locked' => 1]);
        } catch (\Throwable $e) {
            throw new AdminException($e->getMessage());
        }
    }

    /**
     * 解冻用户（平台端）
     */
    public function unLocked(array|string $id): void
    {
        try {
            if (is_string($id)) {
                $id = array_map('trim', explode(',', $id));
            }
            $this->dao->getModel()
                ->withoutGlobalScope(TenantScope::class)
                ->whereIn('id', $id)
                ->update(['is_locked' => 0]);
        } catch (\Throwable $e) {
            throw new AdminException($e->getMessage());
        }
    }

    /**
     * 重置密码（平台端）
     */
    public function batchUpdate(array $ids, array $data): void
    {
        try {
            $this->dao->getModel()
                ->withoutGlobalScope(TenantScope::class)
                ->whereIn('id', $ids)
                ->update($data);
        } catch (\Throwable $e) {
            throw new AdminException($e->getMessage());
        }
    }

    // ========== 以下方法复用 adminapi AdminService 中的相同逻辑 ==========

    private function updatePasswordIfNeeded(array &$data): void
    {
        if (isset($data['password'])) {
            if (empty($data['password'])) {
                unset($data['password']);
            } else {
                $data['password'] = password_hash($data['password'], PASSWORD_DEFAULT);
            }
        }
    }

    private function updateModel(Model $model, array $data, array $depts, array $posts): void
    {
        $model->fill($data);
        $model->save();

        if (!empty($depts)) {
            $model->depts()->sync($depts);
        }
        if (!empty($posts)) {
            $model->posts()->sync($posts);
        }
    }

    private function syncRoles($model, array $roleIds): void
    {
        if (!empty($roleIds)) {
            /** @var AdminRoleService $roleService */
            $roleService = Container::make(AdminRoleService::class);
            $roleService->save(['id' => $model->id, 'role_id_list' => $roleIds]);
        }
    }

    private function syncMainInfo($model, $mainDeptId, $mainPosId): void
    {
        if ($mainDeptId || $mainPosId) {
            $model->mainInfo()->updateOrCreate(
                ['admin_id' => $model->id],
                [
                    'main_dept_id' => $mainDeptId,
                    'main_post_id' => $mainPosId,
                ]
            );
        }
    }
}
