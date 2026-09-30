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
     */
    public function getAllCategories(?string $tenantId = null): array
    {
        $query = Category::where('enabled', 1)
            ->where('pid', 0); // 只取顶级

        if ($tenantId !== null) {
            $query->where(function ($q) use ($tenantId) {
                $q->whereNull('tenant_id')->orWhere('tenant_id', $tenantId);
            });
        } else {
            $query->whereNull('tenant_id');
        }

        $categories = $query->orderBy('sort', 'asc')
                            ->orderBy('id', 'asc')
                            ->get()
                            ->toArray();

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
        $query = Definition::where('category_id', $categoryId)
            ->where('enabled', 1);

        if ($tenantId !== null) {
            $query->where(function ($q) use ($tenantId) {
                $q->whereNull('tenant_id')->orWhere('tenant_id', $tenantId);
            });
        } else {
            $query->whereNull('tenant_id');
        }

        return $query->orderBy('sort', 'asc')
                     ->orderBy('id', 'asc')
                     ->get()
                     ->toArray();
    }

    /**
     * 根据 key 获取分类
     */
    public function getByKey(string $key, ?string $tenantId = null): ?array
    {
        $query = Category::where('key', $key);
        if ($tenantId !== null) {
            $query->where(function ($q) use ($tenantId) {
                $q->whereNull('tenant_id')->orWhere('tenant_id', $tenantId);
            });
        } else {
            $query->whereNull('tenant_id');
        }
        $model = $query->first();
        return $model ? $model->toArray() : null;
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
            'enabled'     => $data['enabled'] ?? 1,
            'tenant_id'   => $data['tenant_id'] ?? null,
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
            'enabled'      => $data['enabled'] ?? 1,
            'tenant_id'    => $data['tenant_id'] ?? null,
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
