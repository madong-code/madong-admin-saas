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
use app\enum\web\MenuTarget;
use Illuminate\Database\Eloquent\SoftDeletes;
use core\foundation\base\BaseModel;

/**
 * 友情链接模型
 */
class Link extends BaseModel
{
    use SoftDeletes;
    /**
     * 数据表名称
     */
    protected $table = 'web_link';

    /**
     * 数据表主键
     */
    protected $primaryKey = 'id';

    /**
     * 可批量赋值的字段
     */
    protected $fillable = [
        'id',
        'name',
        'url',
        'logo',
        'description',
        'category',
        'sort',
        'target',
        'click_count',
        'enabled',
        'created_at',
        'updated_at',
        'deleted_at',
        'tenant_id',
    ];

    /**
     * 类型转换
     */
    protected $casts = [
        'click_count' => 'integer',
        'enabled'     => 'integer',
        'id'          => 'string',
        'sort'        => 'integer',
        'tenant_id'   => 'string',
    ];

    /**
     * 追加字段
     */
    protected $appends = [
        'target_text',
        'enabled_text',
    ];

    /**
     * 获取目标窗口文本
     */
    public function getTargetTextAttribute(): string
    {
        $target = MenuTarget::tryFrom((int)$this->target);
        return $target?->label() ?? '未知';
    }

    /**
     * 获取状态文本
     */
    public function getEnabledTextAttribute(): string
    {
        $status = EnabledStatus::tryFrom((int)$this->enabled);
        return $status?->label() ?? '未知';
    }
}
