<?php
declare(strict_types=1);

/**
 *+------------------
 * madong - 消息分类模型
 *+------------------
 * Copyright (c) https://gitee.com/motion-code  All rights reserved.
 *+------------------
 * Author: Mr. April (405784684@qq.com)
 *+------------------
 * Official Website: https://madong.tech
 */

namespace app\model\content\message;

use core\foundation\base\BaseModel;

/**
 * 消息分类模型（pid 树形）
 * 纯分类用途，不承载消息定义。
 * 子级消息定义通过 MessageDefinition.category_id 关联。
 *
 * @property mixed $definitions
 * @property mixed $children
 * @property mixed $parent
 */
class Category extends BaseModel
{
    protected $table = 'sys_message_category';

    protected $fillable = [
        'id',
        'pid',
        'key',
        'name',
        'icon',
        'description',
        'sort',
        'level',
        'path',
        'is_show',
        'is_system',
        'source',
        'enabled',
        'tenant_id',
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'id'        => 'string',
        'pid'       => 'string',
        'tenant_id' => 'string',
        'sort'      => 'integer',
        'level'     => 'integer',
    ];

    /**
     * 分类下的消息定义列表
     */
    public function definitions(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Definition::class, 'category_id');
    }

    /**
     * 子分类
     */
    public function children(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(self::class, 'pid');
    }

    /**
     * 父分类
     */
    public function parent(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(self::class, 'pid');
    }
}
