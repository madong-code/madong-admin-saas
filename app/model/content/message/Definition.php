<?php
declare(strict_types=1);

/**
 *+------------------
 * madong - 消息定义模型
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
 * 消息定义模型（原 MessageModule）
 * 代表一个"可订阅的消息类型"（如"打卡提醒"、"审批待办"）。
 * 属于分类，通过 category_id 关联 MessageCategory。
 * 每条消息定义可有多条 MessageTemplate（不同渠道的发送配置）。
 */
class Definition extends BaseModel
{
    protected $table = 'sys_message_definition';

    protected $fillable = [
        'id',
        'category_id',
        'key',
        'name',
        'description',
        'default_on',
        'nav_type',
        'nav_value',
        'sort',
        'is_system',
        'source',
        'enabled',
        'tenant_id',
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'id'          => 'string',
        'tenant_id'   => 'string',
        'category_id' => 'string',
        'sort'        => 'integer',
    ];

    /**
     * 所属分类
     */
    public function category(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    /**
     * 关联的模板列表（多对多）
     */
    public function templates(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Template::class, 'sys_message_definition_template', 'definition_id', 'template_id')->using(DefinitionRel::class);
    }
}
