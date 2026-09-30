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

namespace app\model\system\config;

use core\foundation\base\BaseModel;

/**
 * 配置模型
 *
 * @author Mr.April
 * @since  1.0
 */
class Config extends BaseModel
{

    /**
     * 数据表主键
     *
     * @var string
     */
    protected $primaryKey = 'id';

    protected $table = 'sys_config';

    protected $appends = ['created_date', 'updated_date'];

    protected static function booted(): void
    {
        parent::booted();
    }
    protected $casts = [
        'created_by' => 'string',
        'id'         => 'string',
        'tenant_id'  => 'string',
        'updated_by' => 'string',
    ];

    protected $fillable = [
        'id',
        'group_code',
        'code',
        'name',
        'content',
        'is_sys',
        'enabled',
        'sort',
        'tenant_id',
        'template_id',
        'source',
        'created_at',
        'created_by',
        'updated_at',
        'updated_by',
        'deleted_at',
        'remark',
    ];

    /**
     * 分组代码-搜索器
     *
     * @param $query
     * @param $value
     */
    public function scopeGroupCode($query, $value): void
    {
        if (!empty($value)) {
            $queryMethod = is_array($value) ? 'whereIn' : 'where';
            $query->$queryMethod('group_code', $value);
        }
    }

    /**
     * 配置名称-搜索器
     *
     * @param $query
     * @param $value
     */
    public function scopeName($query, $value): void
    {
        if (!empty($value)) {
            $queryMethod = is_array($value) ? 'whereIn' : 'where';
            $query->$queryMethod('name', $value);
        }
    }

    /**
     * 唯一编码-搜索器
     *
     * @param $query
     * @param $value
     */
    public function scopeCode($query, $value): void
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
    public function scopeEnable($query, $value): void
    {
        if ($value !== '') {
            $queryMethod = is_array($value) ? 'whereIn' : 'where';
            $query->$queryMethod('enable', $value);
        }
    }
}
