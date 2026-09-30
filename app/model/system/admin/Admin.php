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

namespace app\model\system\admin;

use app\model\content\message\Message;
use app\model\system\org\Dept;
use app\model\system\org\Post;
use app\model\system\role\Role;
use core\foundation\base\BaseModel;
use core\casbin\model\RuleModel;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * 后台管理员-模型
 */
class Admin extends BaseModel
{
    use SoftDeletes;

    // 完整数据库表名称
    protected $table = 'sys_admin';
    // 主键
    protected $primaryKey = 'id';

    protected $appends = ['created_date', 'updated_date'];


    protected $casts = [
        'backend_setting' => 'array',
        'created_by'      => 'string',
        'dept_id'         => 'string',
        'id'              => 'string',
        'tenant_id'       => 'string',
        'updated_by'      => 'string',
    ];

    protected $fillable = [
        'id',
        'user_name',
        'real_name',
        'nick_name',
        'password',
        'is_super',
        'mobile_phone',
        'email',
        'avatar',
        'signed',
        'dashboard',
        'enabled',
        'login_ip',
        'login_time',
        'backend_setting',
        'created_by',
        'updated_by',
        'created_at',
        'updated_at',
        'deleted_at',
        'sex',
        'remark',
        'birthday',
        'tel',
        'is_locked',
        'tenant_id',
    ];

    /**
     * 判断是否为超级管理员
     *
     * @return bool
     */
    public function isSuperAdmin(): bool
    {
        return (bool)$this->is_super;
    }

    /**
     * 账号-搜索器
     *
     * @param $query
     * @param $value
     */
    public function scopeUserName($query, $value)
    {
        if (!empty($value)) {
            $query->where('user_name', 'like', $value . '%');
        }
    }

    /**
     * 用户昵称-搜索器
     *
     * @param $query
     * @param $value
     */
    public function scopeRealName($query, $value)
    {
        if ($value !== '') {
            $query->where('real_name', 'like', $value . '%');
        }
    }

    /**
     * 用户参数-解析
     *
     * @param $value
     *
     * @return mixed
     */
    public function getBackendSetting($value): mixed
    {
        return json_decode($value ?? '', true);
    }

    /**
     * 用户参数-转换
     *
     * @param $value
     *
     * @return bool|string
     */
    public function setBackendSettingAttr($value): bool|string
    {
        return json_encode($value, JSON_UNESCAPED_UNICODE);
    }

    /**
     * 关联-主归属信息（1:1）
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function mainInfo(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(AdminMain::class, 'admin_id', 'id');
    }

    /**
     * 关联-用户部门
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function depts(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Dept::class, AdminDept::class, 'admin_id', 'dept_id');
    }

    /**
     * 关联-用户职位
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function posts(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Post::class, AdminPost::class, 'admin_id', 'post_id');
    }

    /**
     * 通过中间表关联角色
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function roles(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Role::class, AdminRole::class, 'admin_id', 'role_id');
    }

    /**
     * 关联-管理员类型（通过中间表）
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function adminTypes(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(AdminType::class, AdminTypeRel::class, 'admin_id', 'type_id');
    }

    /**
     * 获取该管理员的所有类型编码（含默认 'admin'）
     *
     * @return array
     */
    public function getTypes(): array
    {
        $types      = ['admin']; // 默认值
        $adminTypes = $this->adminTypes;
        if ($adminTypes && $adminTypes->isNotEmpty()) {
            foreach ($adminTypes as $at) {
                $types[] = $at->code;
            }
        }
        return array_values(array_unique($types));
    }

    /**
     * 判断是否有某个类型编码
     *
     * @param string $code
     *
     * @return bool
     */
    public function hasType(string $code): bool
    {
        return in_array($code, $this->getTypes());
    }

    /**
     * 管理消息-列表
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function message(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Message::class, 'receiver_id', 'id');
    }

}
