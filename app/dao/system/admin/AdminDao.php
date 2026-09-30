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

namespace app\dao\system\admin;

use app\model\system\admin\Admin;
use core\foundation\base\BaseDao;
use core\business\tenant\context\TenantContext;
use InvalidArgumentException;

class AdminDao extends BaseDao
{

    protected function setModel(): string
    {
        return Admin::class;
    }

    /**
     * 用户详情
     *
     * @param                   $id
     * @param array|null        $field
     * @param array|null        $with
     * @param string            $order
     * @param array|null        $withoutScopes
     *
     * @return Admin|null
     * @throws \Exception
     */
    public function get($id, ?array $field = null, ?array $with = [], string $order = '', ?array $withoutScopes = null): Admin|null
    {
        // 关键修复：在构建查询之前移除指定全局作用域，避免 scope->apply() 提前执行
        // （toSql()/first() 会触发 applyScopes，如果 TenantScope 先应用了 WHERE tenant_id，
        //  再调用 withoutGlobalScope 移除时 WHERE 条件不会自动撤回，导致查不到记录）
        $model = $this->getModel();
        if (!empty($withoutScopes)) {
            $query = $model->withoutGlobalScopes($withoutScopes)->where('id', $id);
        } else {
            $query = $model->where('id', $id);
        }

        // 添加with关联（子查询中也移除指定作用域）
        $query->with([
            'depts' => function ($query) use ($withoutScopes) {
                if (!empty($withoutScopes)) {
                    $this->applyScopeRemoval($query, $withoutScopes);
                }
            },
            'posts' => function ($query) use ($withoutScopes) {
                if (!empty($withoutScopes)) {
                    $this->applyScopeRemoval($query, $withoutScopes);
                }
            },
            'roles' => function ($query) use ($withoutScopes) {
                if (!empty($withoutScopes)) {
                    $this->applyScopeRemoval($query, $withoutScopes);
                }
            },
            'mainInfo.mainDept',
            'mainInfo.mainPost',
        ]);

        // 先获取结果，检查是否为null
        $result = $query->first();

        // 如果结果为空，直接返回null
        if (!$result) {
            return null;
        }

        // 隐藏密码字段
        $result->makeHidden(['password']);

        // 初始化属性
        $result->role_id_list = [];
        $result->post_id_list = [];
        $result->dept_id      = '';

        // 处理角色关系
        if (!empty($result->roles)) {
            $result->role_id_list = $result->roles->pluck('id')->all();
        }

        // 处理posts关系
        if (!empty($result->posts)) {
            $result->post_id_list = $result->posts->pluck('id')->all();
        }

        // 处理depts关系
        if (!empty($result->depts) && $result->depts->isNotEmpty()) {
            $result->dept_id = $result->depts->first()->id;
        }

        // 追加主要部门/主要职位名称
        $result->main_dept_name = $result->mainInfo?->mainDept?->name ?? '';
        $result->main_post_name = $result->mainInfo?->mainPost?->name ?? '';

        return $result;

    }

    /**
     * 获取用户列表
     *
     * @param array      $where 查询条件数组(标准格式或三元素数组)
     * @param string     $field
     * @param int        $page
     * @param int        $limit
     * @param string     $order
     * @param array      $with
     * @param bool       $search
     * @param array|null $withoutScopes
     *
     * @return array
     * @throws \Exception
     */
    public function getList(array $where, string $field = '*', int $page = 0, int $limit = 0, string $order = '', array $with = [], bool $search = false, ?array $withoutScopes = null): array
    {
        // 初始化部门ID
        $deptId = null;

        // 兼容两种格式: 标准格式 ['filters' => [...]] 和三元素数组
        if (isset($where['filters']) && is_array($where['filters'])) {
            // 标准格式: 从filters中查找并移除dept_id条件
            foreach ($where['filters'] as $key => $value) {
                if ($key === 'dept_id') {
                    // 解析 dept_id 值
                    $parts  = explode(':', $value, 2);
                    $deptId = $parts[1] ?? $value;
                    // 移除这个条件
                    unset($where['filters'][$key]);
                    break;
                }
            }
        } else {
            // 三元素数组格式: 遍历查找 dept_id 条件
            foreach ($where as $index => $condition) {
                if (is_array($condition) && count($condition) >= 2) {
                    $fieldName = $condition[0];
                    if ($fieldName === 'dept_id') {
                        $deptId = $condition[count($condition) - 1];
                        // 移除这个条件
                        unset($where[$index]);
                        break;
                    }
                }
            }
            // 重新索引数组
            $where = array_values($where);
        }

        if (empty($with)) {
            $with = ['depts', 'posts', 'roles', 'mainInfo.mainDept', 'mainInfo.mainPost'];
        }

        $query = parent::selectModel($where, $field, $page, $limit, $order, $with, $search, $withoutScopes);

        // 如果存在deptId条件，则添加关联条件
        if (!is_null($deptId)) {
            $query->whereHas('depts', function ($q) use ($deptId) {
                $q->where('id', $deptId);
            });
        }

        // 多租户：通过 sys_admin.tenant_id 直接隔离管理员列表
        $tenantId = TenantContext::getTenantId();
        if ($tenantId !== null && !TenantContext::isAdmin()) {
            $query->where('tenant_id', $tenantId);
        }

        $total = $query->count();
        $items = $query->get()->makeHidden(['password', 'backend_setting']);

        // 追加主要部门/主要职位名称（从预加载的 mainInfo 中提取）
        $items->each(function ($item) {
            $item->main_dept_name = $item->mainInfo?->mainDept?->name ?? '';
            $item->main_post_name = $item->mainInfo?->mainPost?->name ?? '';
        });

        return [$total, $items];
    }

    /**
     * 获取用户列表-角色id
     *
     * @param array      $where
     * @param string     $field
     * @param int        $page
     * @param int        $limit
     * @param array|null $withoutScopes
     *
     * @return array
     * @throws \Exception
     */
    public function getUsersListByRoleId(array $where, string $field, int $page, int $limit, ?array $withoutScopes = null): array
    {
        $roleId = $where['role_id'] ?? null;
        if (!$roleId) {
            throw new InvalidArgumentException("Role ID is required.");
        }
        $where['enabled']  = 1;//有效用户
        $where['is_super'] = 0;//非顶级管理员

        $query = $this->getModel()->with(['roles' => function ($query) use ($withoutScopes) {
            $this->applyScopeRemoval($query, $withoutScopes);
        }])
            ->whereHas('roles', function ($query) use ($roleId) {
                $query->where('id', $roleId);
            });

        $this->applyScopeRemoval($query, $withoutScopes);
        if (!empty($where)) {
            unset($where['role_id']);
            $query->where($where);
        }

        $total = $query->count();

        $items = $query->when($page > 0 && $limit > 0, function ($query) use ($page, $limit) {
            return $query->skip(($page - 1) * $limit)->take($limit);
        })
            ->select($field)
            ->get()
            ->toArray();

        return compact('total', 'items');
    }

    /**
     * 排除角色ID-用户列表
     *
     * @param array      $where
     * @param string     $field
     * @param int        $page
     * @param int        $limit
     * @param array|null $withoutScopes
     *
     * @return array
     * @throws \ReflectionException
     */
    public function getUsersExcludingRole(array $where, string $field, int $page, int $limit, ?array $withoutScopes = null): array
    {
        $roleId = $where['role_id'] ?? null;
        if (!$roleId) {
            throw new InvalidArgumentException("Role ID is required.");
        }

        $where['enabled']  = 1;//有效用户
        $where['is_super'] = 0;//非顶级管理员

        // 获取排除的用户ID列表
        $sysAdminRoleDao  = new AdminRoleDao();
        $excludedAdminIds = $sysAdminRoleDao->getColumn(['role_id' => $roleId], 'admin_id');

        // 查询构建器
        $query = $this->getModel()->whereNotIn('id', $excludedAdminIds)
            ->with(['roles' => function ($query) use ($withoutScopes) {
                $this->applyScopeRemoval($query, $withoutScopes);
            }]);
        $this->applyScopeRemoval($query, $withoutScopes);

        // 如果有额外的条件，则添加到查询中
        if (!empty($where)) {
            unset($where['role_id']);
            $query->where($where);
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

    /**
     * @param string|int $id
     *
     * @return \app\model\system\admin\Admin|null
     * @throws \Exception
     */
//    public function getAdminById(string|int $id): ?Admin
//    {
//        $result = $this->getModel()
//            ->where('id', $id)
//            ->with(['depts', 'posts', 'casbin.roles', 'roles'])
//            ->first()
//            ->makeHidden(['password', 'backend_setting']);
//
//        if ($result) {
//            $result->role_id_list = [];
//            $result->post_id_list = [];
//            $result->dept_id      = '';
//
//            // 处理 casbin 关系
//            if ($result->casbin) {
//                $roleIds = $result->casbin->flatMap(function ($item) {
//                    return $item->roles->pluck('id');
//                })->unique()->values()->all();
//
//                $result->role_id_list = $roleIds;
//            }
//
//            // 处理 posts 关系
//            if ($result->posts) {
//                $result->post_id_list = $result->posts->pluck('id')->all();
//            }
//
//            // 处理 depts 关系
//            if ($result->depts && $result->depts->isNotEmpty()) {
//                $result->dept_id = $result->depts->first()->id;
//            }
//        }
//
//        return $result;
//    }
    /**
     * @param string|int $id
     * @param array|null $withoutScopes
     *
     * @return \app\model\system\admin\Admin|null
     * @throws \Exception
     */
    public function getAdminById(string|int $id, ?array $withoutScopes = null): ?Admin
    {
        $query = $this->getModel()
            ->where('id', $id)
            ->with(['depts' => function ($query) use ($withoutScopes) {
                $this->applyScopeRemoval($query, $withoutScopes);
            }, 'posts'      => function ($query) use ($withoutScopes) {
                $this->applyScopeRemoval($query, $withoutScopes);
            }, 'roles'      => function ($query) use ($withoutScopes) {
                $this->applyScopeRemoval($query, $withoutScopes);
            }, 'mainInfo.mainDept', 'mainInfo.mainPost']);

        // 应用作用域移除
        $this->applyScopeRemoval($query, $withoutScopes);

        $result = $query->first();

        if ($result) {
            $result->makeHidden(['password', 'backend_setting']);
            $result->role_id_list = [];
            $result->post_id_list = [];
            $result->dept_id_list = [];
            $result->main_dept_id = null;
            $result->main_post_id = null;

            if (!empty($result->roles)) {
                $result->role_id_list = $result->roles->pluck('id')->all();
            }

            if ($result->posts) {
                $result->post_id_list = $result->posts->pluck('id')->all();
            }

            if ($result->depts && $result->depts->isNotEmpty()) {
                $result->dept_id_list = $result->depts->pluck('id')->all();
            }

            if ($result->mainInfo) {
                $result->main_dept_id = $result->mainInfo->main_dept_id;
                $result->main_post_id = $result->mainInfo->main_post_id;
                $result->main_dept_name = $result->mainInfo->mainDept?->name ?? '';
                $result->main_post_name = $result->mainInfo->mainPost?->name ?? '';
            }
        }
        return $result;
    }

    /**
     * 获取用户详情
     *
     * @param string     $name
     * @param array|null $withoutScopes
     *
     * @return \app\model\system\admin\Admin|null
     * @throws \Exception
     */
    public function getAdminByName(string $name, ?array $withoutScopes = null, array $extraWhere = []): ?Admin
    {
        $query = $this->getModel()
            ->where('user_name', $name);

        // 额外条件（如 tenant_id 过滤）
        foreach ($extraWhere as $field => $value) {
            $query->where($field, $value);
        }

        $query->with(['depts' => function ($query) use ($withoutScopes) {
            $this->applyScopeRemoval($query, $withoutScopes);
        }, 'posts'      => function ($query) use ($withoutScopes) {
            $this->applyScopeRemoval($query, $withoutScopes);
        }, 'roles'      => function ($query) use ($withoutScopes) {
            $this->applyScopeRemoval($query, $withoutScopes);
        }]);

        // 应用作用域移除
        $this->applyScopeRemoval($query, $withoutScopes);
        return $query->first();
    }

}
