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
namespace app\model\web;

use app\enum\common\EnabledStatus;
use app\enum\common\YesNoStatus;
use app\enum\system\MenuType;
use app\enum\web\MenuCategory;
use app\enum\web\MenuTarget;
use core\foundation\base\BaseModel;

/**
 * 菜单模型
 */
class Menu extends BaseModel
{
    /**
     * 允许 tenant_id = NULL 的记录对所有租户可见
     * NULL 表示全局共享菜单，非 NULL 表示租户自定义菜单
     */
    public bool $allowNullTenant = true;

    /**
     * 数据表名称
     */
    protected $table = 'web_menu';

    /**
     * 数据表主键
     */
    protected $primaryKey = 'id';

    /**
     * 可批量赋值的字段
     */
    protected $fillable = [
        'id',
        'app',
        'category',
        'source',
        'code',
        'is_public',
        'is_no_auth',
        'name',
        'url',
        'pid',
        'level',
        'type',
        'sort',
        'target',
        'icon',
        'is_show',
        'enabled',
        'permissions',
        'extra',
        'created_at',
        'updated_at',
        'deleted_at',
        'tenant_id',
    ];

    /**
     * 追加字段
     */
    protected $appends = [
        'type_text',
        'target_text',
        'is_show_text',
        'enabled_text',
        'category_text',
    ];


    protected $casts = [
        'extra'     => 'json',
        'id'        => 'string',
        'pid'       => 'string',
        'tenant_id' => 'string',
    ];

    /**
     * 获取导航类型文本
     */
    public function getTypeTextAttribute(): string
    {
        $type = MenuType::tryFrom((int)$this->type);
        return $type?->label() ?? '未知';
    }

    /**
     * 获取目标窗口文本
     */
    public function getTargetTextAttribute(): string
    {
        $target = MenuTarget::tryFrom((int)$this->target);
        return $target?->label() ?? '未知';
    }

    /**
     * 获取显示状态文本
     */
    public function getIsShowTextAttribute(): string
    {
        $show = YesNoStatus::tryFrom((int)$this->is_show);
        return $show?->label() ?? '未知';
    }

    /**
     * 获取状态文本
     */
    public function getEnabledTextAttribute(): string
    {
        $status = EnabledStatus::tryFrom((int)$this->enabled);
        return $status?->label() ?? '未知';
    }

    /**
     * 获取分类文本
     */
    public function getCategoryTextAttribute(): string
    {
        $category = MenuCategory::tryFrom((int)$this->category);
        return $category?->label() ?? '未知';
    }

    /**
     * 定义子级关系
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function children(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(self::class, 'pid');
    }

    /**
     * 获取所有子级ID（递归）
     *
     * @return array
     */
    protected function getAllChildrenIds(): array
    {
        $childIds = $this->children()->pluck('id')->toArray();
        foreach ($this->children as $child) {
            $childIds = array_merge($childIds, $child->getAllChildrenIds());
        }
        return array_unique($childIds);
    }

    /**
     * 删除菜单及所有子级
     *
     * @return array|bool 成功删除的ID数组或false
     */
    public function deleteWithAllChildren(): array|bool
    {
        $allIds = array_merge([$this->id], $this->getAllChildrenIds());

        if (empty($allIds)) {
            return false;
        }

        $successIds = [];

        // 分块处理以避免内存问题
        collect($allIds)->chunk(100)->each(function ($chunk) use (&$successIds) {
            $chunk->each(function ($id) use (&$successIds) {
                if ($model = self::find($id)) {
                    if ($model->delete()) {
                        $successIds[] = $id;
                    }
                }
            });
        });

        return !empty($successIds) ? $successIds : false;
    }
}
