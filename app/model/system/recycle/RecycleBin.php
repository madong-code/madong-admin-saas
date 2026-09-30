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

namespace app\model\system\recycle;

use app\model\system\admin\Admin;
use core\foundation\base\BaseModel;
use core\business\tenant\context\TenantContext;

/**
 * 附件模型
 *
 * @author Mr.April
 * @since  1.0
 */
class RecycleBin extends BaseModel
{

    /**
     * 数据表主键
     *
     * @var string
     */
    protected $primaryKey = 'id';

    protected $table = 'sys_recycle_bin';

    protected $appends = ['created_date', 'updated_date', 'operate_name'];

    protected $fillable = [
        'id',
        'original_id',
        'data',
        'relation_data',
        'table_name',
        'table_prefix',
        'enabled',
        'ip',
        'operate_by',
        'created_at',
        'updated_at',
        'tenant_id',
    ];


    protected $casts = [
        'data'          => 'array',
        'relation_data' => 'array',
        'id'            => 'string',
        'operate_by'    => 'string',
        'original_id'   => 'string',
        'tenant_id'     => 'string',
    ];

//    // 1. 定义访问器/修改器
//    public function getDataAttribute($value)
//    {
//        return json_decode($value, true) ?? [];
//    }
//
//    public function setDataAttribute($value)
//    {
//        $this->attributes['data'] = json_encode($value);
//    }

    /**
     * 动态数据库链接(主库)
     *
     * @return \app\common\model\system\SysRecycleBin
     */
    public static function centralConnection(): RecycleBin
    {
        return (new static)->setConnection(config('database.default'));
    }

    /**
     * 动态数据库连接
     * 根据租户模式返回对应连接
     */
    public function getDynamicConnectionName()
    {
        $tenantMode = TenantContext::getIsolationMode();
        $tenantId   = TenantContext::getTenantId();

        if ($tenantMode === 'database' && $tenantId) {
            return 'tenant_' . $tenantId;
        }

        return config('database.default');
    }

    /**
     * 定义访问器
     *
     * @return null
     */
    public function getOperateNameAttribute(): mixed
    {
        return $this->operate ? $this->operate->operate_name : null;
    }

    /**
     * 关联-操作人
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function operate(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Admin::class, 'id', 'operate_by')->select(['id', 'real_name as operate_name']);
    }
}
