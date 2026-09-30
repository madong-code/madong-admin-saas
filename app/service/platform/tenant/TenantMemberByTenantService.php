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
namespace app\service\platform\tenant;

use app\dao\tenant\TenantDao;
use app\model\system\admin\Admin;
use core\foundation\base\BaseService;
use core\foundation\exception\handler\AdminException;
use core\business\tenant\SyncConnection;
use core\business\tenant\TenantConnectionManager;
use Illuminate\Database\QueryException;
use support\Container;

/**
 * 按租户隔离的成员管理 Service
 *
 * 根据租户的 database_mode 自动切换到对应的库表：
 * - database 模式：通过 SyncConnection 连接到租户独立库，查询该库的 sys_admin 表
 * - field 模式：在主库的 sys_admin 表中按 tenant_id 过滤
 */
class TenantMemberByTenantService extends BaseService
{
    private TenantDao $tenantDao;
    private int $tenantId;
    private string $connection;

    public function __construct()
    {
        $this->tenantDao = Container::make(TenantDao::class);
    }

    /**
     * 初始化租户上下文
     */
    public function initWithTenant(int $tenantId): void
    {
        $this->tenantId = $tenantId;
        $tenant = $this->tenantDao->get($tenantId);
        if (!$tenant) {
            throw new \RuntimeException('租户不存在');
        }
        $mode = $tenant->database_mode ?? 'field';
        $this->connection = SyncConnection::getConnectionName($tenantId, $mode);
    }

    /**
     * 获取模型实例（框架 Crud 基类的 selectInput/inputFilter 依赖此方法）
     */
    public function getModel(): \Illuminate\Database\Eloquent\Model
    {
        return new Admin();
    }

    /**
     * 获取当前连接
     */
    public function getConnection(): string
    {
        return $this->connection;
    }

    /**
     * 是否为主库（字段隔离模式）
     *
     * 字段模式下所有租户共用主库，需按 tenant_id 过滤；
     * 库隔离模式下各自独立数据库，无需 tenant_id 过滤。
     * 通过配置的实际默认连接名判断，避免硬编码 'mysql'。
     */
    private function isMainConnection(): bool
    {
        return $this->connection === TenantConnectionManager::getDefaultConnectionName();
    }

    /**
     * 获取当前租户ID
     */
    public function getTenantId(): int
    {
        return $this->tenantId;
    }

    /**
     * 对查询构建器应用 Crud 框架的 where 条件
     *
     * Crud 的 selectInput 返回的 where 可能包含：
     * - ['filters' => [...]] — 框架生成的 filter 条件
     * - [['field', 'op', 'val'], ...] — 三元素数组格式
     * - ['field' => 'value', ...] — 普通键值对
     */
    private function applyWhere(\Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder $query, array $where): void
    {
        // 处理 filters 格式
        if (isset($where['filters']) && is_array($where['filters'])) {
            foreach ($where['filters'] as $field => $filter) {
                // keyvalue 格式: [field => 'op:value']
                if (is_string($field) && is_string($filter) && str_contains($filter, ':')) {
                    [$op, $val] = explode(':', $filter, 2);
                    $query->where($field, $op, $val);
                }
                // 三元素数组: [field, operator, value]
                elseif (is_array($filter) && count($filter) === 3) {
                    $query->where($filter[0], $filter[1], $filter[2]);
                }
            }
        }

        // 处理三元素数组和普通键值对
        $skipKeys = ['filters', 'format', 'limit', 'field', 'order', 'page'];
        foreach ($where as $key => $value) {
            if (in_array($key, $skipKeys, true)) {
                continue;
            }
            if (is_numeric($key) && is_array($value) && count($value) >= 2) {
                $query->where($value[0], $value[1], $value[2] ?? null);
            } elseif (!is_array($value)) {
                $query->where($key, $value);
            }
        }
    }

    /**
     * 列表查询
     */
    public function selectList(array $where = [], string $_field = '*', int $page = 1, int $limit = 15, array|string $order = [], array $_with = [], bool $withTotal = false): array
    {
        $query = Admin::on($this->connection);

        // field 模式需要按 tenant_id 过滤
        if ($this->isMainConnection()) {
            $query->where('tenant_id', $this->tenantId);
        }

        // 应用查询条件
        $this->applyWhere($query, $where);

        // 排序
        if (is_string($order) && !empty($order)) {
            $parts = explode(',', $order);
            foreach ($parts as $part) {
                $part = trim($part);
                if (preg_match('/^(\S+)\s+(ASC|DESC)$/i', $part, $m)) {
                    $query->orderBy($m[1], $m[2]);
                } else {
                    $query->orderBy($part);
                }
            }
        }

        $total = $query->count();
        $items = $query->skip(($page - 1) * $limit)->take($limit)->get()->toArray();

        return $withTotal ? ['list' => $items, 'total' => $total] : $items;
    }

    /**
     * 获取总数
     */
    public function getCount(array $where = []): int
    {
        $query = Admin::on($this->connection);

        if ($this->isMainConnection()) {
            $query->where('tenant_id', $this->tenantId);
        }

        $this->applyWhere($query, $where);

        return $query->count();
    }

    /**
     * 获取单条记录
     */
    public function get(int|string $id): mixed
    {
        $query = Admin::on($this->connection)->where('id', $id);

        if ($this->isMainConnection()) {
            $query->where('tenant_id', $this->tenantId);
        }

        $result = $query->first();
        if ($result) {
            $result->makeHidden(['password']);
        }
        return $result;
    }

    /**
     * 新增成员
     */
    public function save(array $data): mixed
    {
        // 前端传 is_super（0/1），默认普通用户
        $data['is_super'] = !empty($data['is_super']) ? 1 : 0;

        // 检查用户名在租户范围内是否唯一（含已软删除记录）
        $this->checkUserNameUnique($data['user_name'] ?? null, null);

        try {
            return $this->transaction(function () use ($data) {
                if ($this->isMainConnection()) {
                    $data['tenant_id'] = $this->tenantId;
                }
                return Admin::on($this->connection)->create($data);
            }, true, $this->connection);
        } catch (QueryException $e) {
            // 兜底：并发写入或软删除残留导致的唯一索引冲突，避免将原始 SQL 错误暴露给前端
            if ($e->getCode() === '23000' || ($e->errorInfo[1] ?? null) === 1062) {
                throw new \RuntimeException("用户名 '{$data['user_name']}' 在该租户下已存在");
            }
            throw $e;
        }
    }

    /**
     * 检查用户名在租户范围内是否唯一
     *
     * 注意：Admin 模型使用了 SoftDeletes，唯一索引建立在 (user_name, tenant_id) 上。
     * 已软删除的记录在数据库层面仍占用唯一索引，因此此处必须用 withTrashed()
     * 才能覆盖“删除后又用相同用户名重建”的场景，否则会在 insert 时才被数据库拦截。
     */
    private function checkUserNameUnique(?string $userName, ?string $excludeId): void
    {
        if ($userName === null) {
            return;
        }
        $query = Admin::on($this->connection)->withTrashed()->where('user_name', $userName);
        if ($this->isMainConnection()) {
            $query->where('tenant_id', $this->tenantId);
        }
        if ($excludeId !== null) {
            $query->where('id', '!=', $excludeId);
        }
        if ($query->exists()) {
            throw new \RuntimeException("用户名 '{$userName}' 在该租户下已存在");
        }
    }

    /**
     * 更新成员
     */
    public function update(int|string $id, array $data): mixed
    {
        // 检查用户名在租户范围内是否唯一（排除自身）
        $this->checkUserNameUnique($data['user_name'] ?? null, (string)$id);

        return $this->transaction(function () use ($id, $data) {
            if (empty($data['password'])) {
                unset($data['password']);
            }
            unset($data['tenant_id']);

            // is_super 直接存储 0/1
            if (isset($data['is_super'])) {
                $data['is_super'] = !empty($data['is_super']) ? 1 : 0;
            }

            $query = Admin::on($this->connection)->where('id', $id);
            if ($this->isMainConnection()) {
                $query->where('tenant_id', $this->tenantId);
            }
            return $query->update($data);
        }, true, $this->connection);
    }

    /**
     * 批量删除
     */
    public function batchDelete(array $ids): array
    {
        return $this->transaction(function () use ($ids) {
            $query = Admin::on($this->connection)->whereIn('id', $ids);
            if ($this->isMainConnection()) {
                $query->where('tenant_id', $this->tenantId);
            }
            $query->delete();
            return ['ids' => $ids];
        }, true, $this->connection);
    }

    /**
     * 修改状态
     */
    public function changStatus(int|string $id, int $enabled): void
    {
        $query = Admin::on($this->connection)->where('id', $id);
        if ($this->isMainConnection()) {
            $query->where('tenant_id', $this->tenantId);
        }
        $model = $query->first();
        if (!$model) throw new \RuntimeException('用户不存在');
        if ($model->is_super == 1) {
            throw new AdminException('系统内置用户不允许操作');
        }
        $model->update(['enabled' => $enabled]);
    }

    /**
     * 锁定
     */
    public function locked(array $ids): void
    {
        $query = Admin::on($this->connection)->whereIn('id', $ids);
        if ($this->isMainConnection()) {
            $query->where('tenant_id', $this->tenantId);
        }
        $superCount = (clone $query)->where('is_super', 1)->count();
        if ($superCount > 0) {
            throw new AdminException('系统内置用户不允许锁定');
        }
        Admin::on($this->connection)->whereIn('id', $ids)->update(['is_locked' => 1]);
    }

    /**
     * 解锁
     */
    public function unLocked(array $ids): void
    {
        Admin::on($this->connection)->whereIn('id', $ids)->update(['is_locked' => 0]);
    }

    /**
     * 重置密码
     */
    public function resetPassword(array $ids): void
    {
        $defaultPwd = password_hash('123456', PASSWORD_DEFAULT);
        Admin::on($this->connection)->whereIn('id', $ids)->update(['password' => $defaultPwd]);
    }

}
