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
 * Official Website: http://www.madong.cn
 */

namespace app\model\system\menu;

use app\model\system\role\Role;
use app\model\system\role\RoleMenu;
use core\foundation\base\BaseModel;

/**
 * 全局菜单
 *
 * @property mixed $children
 * @property mixed $id
 * @author Mr.April
 * @since  1.0
 */
class Menu extends BaseModel
{
    protected $table = 'sys_menu';

    protected $primaryKey = 'id';

    protected $appends = ['created_date', 'updated_date'];

    protected $fillable = [
        'id',
        'pid',
        'template_id',
        'app',
        'title',
        'code',
        'level',
        'type',
        'sort',
        'path',
        'component',
        'redirect',
        'icon',
        'is_show',
        'is_tab',
        'is_link',
        'link_url',
        'open_type',
        'is_cache',
        'is_sync',
        'is_affix',
        'variable',
        'created_at',
        'created_by',
        'updated_at',
        'updated_by',
        'deleted_at',
        'methods',
        'source',
        'platform',
        'tenant_id',
    ];


    protected $casts = [
        'created_by'  => 'string',
        'id'          => 'string',
        'pid'         => 'string',
        'template_id' => 'string',
        'tenant_id'   => 'string',
        'updated_by'  => 'string',
    ];

    /**
     * 菜单meta属性
     *
     * @param $data
     *
     * @return array
     */
    public static function getMetaAttribute($data): array
    {
        // 1.构建mate数组
        $title   = trans($data['title'], [], 'menu');
        $newData = [
            'icon'                     => $data['icon'] ?? '',
            'title'                    => $title,
            'menuVisibleWithForbidden' => true,
            'keepAlive'                => true,
        ];

        // 2.添加fixed锁定菜单标记
        if (isset($data['is_affix']) && ($data['is_affix'] === 1 || $data['is_affix'] === '1')) {
            $newData['order']    = -1;
            $newData['affixTab'] = true;
        }

        // 3.是否隐藏菜单
        if (isset($data['is_show']) && $data['is_show'] == 0) {
            $newData['hideInMenu'] = true;
        }

        // 4.是否隐藏标签页(is_tab=0 不显示在 tags 标签页)
        if (isset($data['is_tab']) && $data['is_tab'] == 0) {
            $newData['hideInTab'] = true;
        }

        // 5.是否缓存
        if (isset($data['is_cache']) && (int)$data['is_cache'] == 1) {
            $newData['keepAlive'] = true;
        }

        // 6.是否外链在新窗口打开
        if (isset($data['open_type']) && $data['open_type'] == 1) {
            $newData['link'] = true;
        }
        // 更多参数可以在这边添加
        return $newData;
    }

    /**
     * Id搜索
     */
    public function scopeId($query, $value)
    {
        if (is_array($value)) {
            $query->whereIn('id', $value);
        } else {
            $query->where('id', $value);
        }
    }

    /**
     * Type搜索
     */
    public function scopeType($query, $value)
    {
        if (is_array($value)) {
            $query->whereIn('type', $value);
        } else {
            $query->where('type', $value);
        }
    }

    public function scopeEnabled($query, $value)
    {
        if (is_array($value)) {
            $query->whereIn('enabled', $value);
        } else {
            $query->where('enabled', $value);
        }
    }


    /**
     * 自定义删除-支持模型事件
     *
     * @return array|bool
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
                        // 触发模型事件
                        $successIds[] = $id;
                    }
                }
            });
        });

        return !empty($successIds) ? $successIds : false;
    }

    /**
     * 获取子级ids
     *
     * @return array
     */
    protected function getAllChildrenIds(): array
    {
        $childIds = $this->children()->pluck('id')->toArray(); // 获取直接子级的ID
        foreach ($this->children as $child) { // 遍历直接子级
            $childIds = array_merge($childIds, $child->getAllChildrenIds()); // 递归获取嵌套子级
        }
        return array_unique($childIds);
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
     * 通过中间表获取角色
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function roles(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Role::class, RoleMenu::class, 'menu_id', 'role_id');
    }
}