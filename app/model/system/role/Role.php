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

namespace app\model\system\role;

use app\model\system\menu\Menu;
use app\model\system\org\Dept;
use core\foundation\base\BaseModel;

/**
 * 角色模型
 *
 * @author Mr.April
 * @since  1.0
 */
class Role extends BaseModel
{

    /**
     * 数据表主键
     *
     * @var string
     */
    protected $primaryKey = 'id';

    protected $table = 'sys_role';

    protected $appends = ['created_date', 'updated_date'];

    protected $fillable = [
        'id',
        'pid',
        'name',
        'code',
        'role_type',
        'data_scope',
        'tenant_id',
        'enabled',
        'sort',
        'remark',
        'created_by',
        'updated_by',
        'created_at',
        'updated_at',
        'deleted_at',
    ];


    protected $casts = [
        'created_by' => 'string',
        'id'         => 'string',
        'pid'        => 'string',
        'tenant_id'  => 'string',
        'updated_by' => 'string',
    ];

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
     * 角色名称-搜索器
     *
     * @param $query
     * @param $value
     */
    public function scopeName($query, $value)
    {
        if (!empty($value)) {
            $query->where('name', 'like', $value . '%');
        }
    }

    /**
     * 状态-搜索器
     *
     * @param $query
     * @param $value
     */
    public function scopeStatus($query, $value)
    {
        if ($value !== '') {
            $query->where('status', $value);
        }
    }

    /**
     * 通过中间表获取菜单
     */
    public function menus(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Menu::class, RoleMenu::class, 'role_id', 'menu_id')->orderBy('sort');
    }

    /**
     * 通过中间表获取部门
     */
    public function depts(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Dept::class, RoleDept::class, 'role_id', 'dept_id');
    }

    /**
     * 通过中间表获取部门
     */
    public function scopes(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Dept::class, RoleScopeDept::class, 'role_id', 'dept_id');
    }



}
