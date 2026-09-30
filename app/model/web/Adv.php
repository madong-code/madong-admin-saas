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

use Illuminate\Database\Eloquent\SoftDeletes;
use core\foundation\base\BaseModel;

/**
 * 广告模型
 */
class Adv extends BaseModel
{
    use SoftDeletes;
    /**
     * 数据表名称
     */
    protected $table = 'web_adv';

    /**
     * 数据表主键
     */
    protected $primaryKey = 'id';

    /**
     * 可批量赋值的字段
     */
    protected $fillable = [
        'id',
        'title',
        'link',
        'image',
        'description',
        'sort',
        'enabled',
        'start_time',
        'end_time',
        'created_at',
        'updated_at',
        'deleted_at',
        'tenant_id',
    ];


    protected $casts = [
        'id'        => 'string',
        'tenant_id' => 'string',
    ];

    /**
     * 追加字段
     */
    protected $appends = [
        'enabled_text',
    ];

    /**
     * 状态映射
     */
    const STATUS_ENABLED = 1;  // 启用
    const STATUS_DISABLED = 0; // 禁用

    /**
     * 获取状态文本
     */
    public function getEnabledTextAttribute(): string
    {
        $statuses = [
            self::STATUS_ENABLED => '启用',
            self::STATUS_DISABLED => '禁用',
        ];
        return $statuses[$this->enabled] ?? '未知';
    }
}