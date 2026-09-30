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

use core\foundation\base\BaseModel;

/**
 * 管理员类型关联-模型
 *
 * @property int $admin_id 管理员ID
 * @property int $type_id 类型ID
 * @property int $created_at 创建时间
 */
class AdminTypeRel extends BaseModel
{
    // 表名
    protected $table = 'sys_admin_type_rel';
    // 复合主键，不需要自增
    protected $primaryKey = null;
    public $incrementing = false;
    // 不自动维护时间戳
    public $timestamps = false;

    protected $fillable = [
        'admin_id',
        'type_id',
        'created_at',
        'tenant_id',
    ];


    protected $casts = [
        'admin_id'  => 'string',
        'tenant_id' => 'string',
        'type_id'   => 'string',
    ];

    /**
     * 关联管理员
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function admin(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Admin::class, 'admin_id', 'id');
    }

    /**
     * 关联类型定义
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function adminType(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(AdminType::class, 'type_id', 'id');
    }
}
