<?php
declare(strict_types=1);

/**
 *+------------------
 * madong - 消息分类服务（后台管理）
 *+------------------
 * Copyright (c) https://gitee.com/motion-code  All rights reserved.
 *+------------------
 * Author: Mr. April (405784684@qq.com)
 *+------------------
 * Official Website: https://madong.tech
 */

namespace app\service\admin\content\message;

use app\dao\content\message\MessageCategoryDao;
use app\dao\content\message\MessageDefinitionDao;
use app\model\content\message\Category;
use app\model\content\message\Definition;
use app\scope\global\TenantScope;
use core\business\tenant\context\TenantContext;
use core\foundation\base\BaseService;

/**
 * 消息分类/定义服务（后台管理）
 *
 * - sys_message_category: 分类表（pid 树形）
 * - sys_message_definition: 消息定义表（原 module），通过 category_id 关联
 */
class CategoryService extends BaseService
{
    protected MessageDefinitionDao $definitionDao;

    public function __construct(MessageCategoryDao $dao, MessageDefinitionDao $definitionDao)
    {
        $this->dao            = $dao;
        $this->definitionDao  = $definitionDao;
    }

    /**
     * 获取所有启用的分类（含定义列表，树形结构）
     *
     * 消息数据按租户隔离：tenant_id=0 为平台默认数据，tenant_id=T 为租户私有数据。
     * 同 key 同时存在时优先返回租户行，避免平台默认项与租户副本重复展示。
     */
    public function getAllCategories(?string $tenantId = null): array
    {
        $categories = $this->tenantScopeQuery(Category::where('enabled', 1), $tenantId)
            ->where('pid', 0) // 只取顶级
            ->orderBy('sort', 'asc')
            ->orderBy('id', 'asc')
            ->get()
            ->toArray();

        $categories = $this->preferTenantRows($categories, 'key');

        // 为每个分类加载消息定义列表
        foreach ($categories as &$category) {
            $category['definitions'] = $this->getDefinitionsByCategory($category['id'], $tenantId);
        }
        unset($category);

        return $categories;
    }

    /**
     * 获取指定分类下的消息定义列表
     */
    public function getDefinitionsByCategory(int|string $categoryId, ?string $tenantId = null): array
    {
        $definitions = $this->tenantScopeQuery(Definition::where('category_id', $categoryId)->where('enabled', 1), $tenantId)
            ->orderBy('sort', 'asc')
            ->orderBy('id', 'asc')
            ->get()
            ->toArray();

        return $this->preferTenantRows($definitions, 'key');
    }

    /**
     * 根据 key 获取分类
     */
    public function getByKey(string $key, ?string $tenantId = null): ?array
    {
        $model = $this->tenantScopeQuery(Category::where('key', $key), $tenantId)
            ->orderByRaw('CASE WHEN tenant_id = 0 THEN 1 ELSE 0 END')
            ->first();

        return $model ? $model->toArray() : null;
    }

    /**
     * 构造 0/T 可见性查询：0=平台默认，T=租户私有，租户行优先
     *
     * 需要移除 TenantScope，否则字段隔离下会被强制追加 tenant_id=T，导致平台默认数据不可见。
     */
    protected function tenantScopeQuery($query, ?string $tenantId = null)
    {
        $query = $query->withoutGlobalScope(TenantScope::class);

        if ($tenantId !== null && $tenantId !== '') {
            return $query->whereIn('tenant_id', ['0', (string)$tenantId]);
        }

        return $query->where('tenant_id', 0);
    }

    /**
     * 同 key 多行时优先保留租户行（tenant_id 非 0），并按排序保持稳定
     */
    protected function preferTenantRows(array $rows, string $keyField): array
    {
        $result = [];
        foreach ($rows as $row) {
            $key = (string)($row[$keyField] ?? '');
            if ($key === '') {
                $result[] = $row;
                continue;
            }

            if (!isset($result[$key])) {
                $result[$key] = $row;
                continue;
            }

            // 已存在平台默认行时，用租户行替换
            if ((string)($result[$key]['tenant_id'] ?? '0') === '0'
                && (string)($row['tenant_id'] ?? '0') !== '0') {
                $result[$key] = $row;
            }
        }

        return array_values($result);
    }

    /**
     * 根据 ID 获取分类
     */
    public function getById(int|string $id): ?array
    {
        $model = Category::find($id);
        return $model ? $model->toArray() : null;
    }

    /**
     * 根据消息定义 ID 获取定义信息（含导航）
     */
    public function getDefinitionInfo(int|string $definitionId): ?array
    {
        $model = Definition::find($definitionId);
        return $model ? $model->toArray() : null;
    }

    /**
     * 创建自定义分类
     */
    public function createCategory(array $data): array
    {
        $pid = $data['pid'] ?? 0;
        $level = 0;
        $path = '0';
        if ($pid > 0) {
            $parent = Category::find($pid);
            if ($parent) {
                $level = $parent->level + 1;
                $path = $parent->path ? $parent->path . '-' . $pid : '0-' . $pid;
            }
        }

        $model = Category::create([
            'pid'         => $pid,
            'key'         => $data['key'],
            'name'        => $data['name'],
            'icon'        => $data['icon'] ?? null,
            'description' => $data['description'] ?? '',
            'sort'        => $data['sort'] ?? 0,
            'level'       => $level,
            'path'        => $path,
            'is_show'     => $data['is_show'] ?? 1,
            'is_system'   => 0,
            'source'      => 'user',
            'enabled'     => $data['enabled'] ?? 1,
            'tenant_id'   => $data['tenant_id'] ?? TenantContext::getTenantId() ?? 0,
            'created_at'  => time(),
            'updated_at'  => time(),
        ]);
        return $model->toArray();
    }

    /**
     * 更新分类
     */
    public function updateCategory(int|string $id, array $data): array
    {
        $model = Category::findOrFail($id);
        if ($model->is_system && isset($data['key'])) {
            unset($data['key']);
        }
        $model->fill($data);
        $model->updated_at = time();
        $model->save();
        return $model->toArray();
    }

    /**
     * 删除分类（系统内置/有子分类/有关联定义时不可删除）
     */
    public function deleteCategory(int|string $id): bool
    {
        $model = Category::findOrFail($id);
        if ($model->is_system) {
            throw new \RuntimeException('系统内置分类不可删除');
        }
        if ($model->children()->count() > 0) {
            throw new \RuntimeException('该分类下有子分类，请先删除子分类');
        }
        if ($model->definitions()->count() > 0) {
            throw new \RuntimeException('该分类下有关联的消息定义，无法删除');
        }
        return $model->delete();
    }

    /**
     * 创建消息定义
     */
    public function createDefinition(array $data): array
    {
        $model = Definition::create([
            'category_id'  => $data['category_id'],
            'key'          => $data['key'],
            'name'         => $data['name'],
            'description'  => $data['description'] ?? '',
            'default_on'   => $data['default_on'] ?? true,
            'nav_type'     => $data['nav_type'] ?? null,
            'nav_value'    => $data['nav_value'] ?? null,
            'sort'         => $data['sort'] ?? 0,
            'is_system'    => 0,
            'source'       => 'user',
            'enabled'      => $data['enabled'] ?? 1,
            'tenant_id'    => $data['tenant_id'] ?? TenantContext::getTenantId() ?? 0,
            'created_at'   => time(),
            'updated_at'   => time(),
        ]);
        return $model->toArray();
    }

    /**
     * 更新消息定义
     */
    public function updateDefinition(int|string $id, array $data): array
    {
        $model = Definition::findOrFail($id);
        $model->fill($data);
        $model->updated_at = time();
        $model->save();
        return $model->toArray();
    }

    /**
     * 删除消息定义
     */
    public function deleteDefinition(int|string $id): bool
    {
        $model = Definition::findOrFail($id);
        if ($model->is_system) {
            throw new \RuntimeException('系统内置定义不可删除');
        }
        return $model->delete();
    }
}
