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

namespace app\model\system\dict;

use core\foundation\base\BaseModel;

/**
 * 字典模型
 *
 * @author Mr.April
 * @since  1.0
 */
class Dict extends BaseModel
{

    /**
     * 数据表主键
     *
     * @var string
     */
    protected $primaryKey = 'id';

    protected $table = 'sys_dict';

    protected $appends = ['created_date', 'updated_date'];

    protected $fillable = [
        'id',
        'group_code',
        'name',
        'code',
        'sort',
        'data_type',
        'description',
        'enabled',
        'template_id',
        'created_by',
        'updated_by',
        'created_at',
        'updated_at',
        'deleted_at',
        'tenant_id',
    ];


    protected $casts = [
        'created_by' => 'string',
        'id'         => 'string',
        'tenant_id'  => 'string',
        'updated_by' => 'string',
    ];    public static function boot()
    {
        parent::boot();
        static::deleting(function ($model) {
            //删除主模型关联删除字典数据
            $model->items()->delete();
        });
    }

    /**
     * ID-搜索器
     *
     * @param $query
     * @param $value
     */
    public function scopeId($query, $value)
    {
        if (!empty($value)) {
            $queryMethod = is_array($value) ? 'whereIn' : 'where';
            $query->$queryMethod('id', $value);
        }
    }

    /**
     * 字典名称-搜索器
     *
     * @param $query
     * @param $value
     */
    public function scopeName($query, $value)
    {
        if (!empty($value)) {
            $queryMethod = is_array($value) ? 'whereIn' : 'where';
            $query->$queryMethod('name', $value);
        }
    }

    /**
     * 字典代码-搜索器
     *
     * @param $query
     * @param $value
     */
    public function scopeCode($query, $value)
    {
        if (!empty($value)) {
            $queryMethod = is_array($value) ? 'whereIn' : 'where';
            $query->$queryMethod('code', $value);
        }
    }

    /**
     * 状态-搜索器
     *
     * @param $query
     * @param $value
     */
    public function scopeEnable($query, $value)
    {
        if ($value !== '') {
            $queryMethod = is_array($value) ? 'whereIn' : 'where';
            $query->$queryMethod('enable', $value);
        }
    }

    /**
     * 字典数据
     */
    public function items(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(DictItem::class, 'dict_id', 'id');
    }

}
