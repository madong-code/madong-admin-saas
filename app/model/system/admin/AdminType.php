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

use core\foundation\base\SystemModel;

/**
 * 管理员类型定义-模型
 *
 * @property int $id 主键ID
 * @property string $code 类型编码
 * @property string $name 类型名称
 * @property int $sort 排序
 * @property int $created_at 创建时间
 * @property int $updated_at 更新时间
 */
class AdminType extends SystemModel
{
    // 表名
    protected $table = 'sys_admin_type';
    // 不自动维护时间戳
    public $timestamps = false;

    protected $fillable = [
        'id',
        'code',
        'name',
        'sort',
        'created_at',
        'updated_at',
    ];


    protected $casts = [
        'id' => 'string',
    ];

    /**
     * 关联管理员（通过中间表）
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function admins(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Admin::class, AdminTypeRel::class, 'type_id', 'admin_id');
    }
}
