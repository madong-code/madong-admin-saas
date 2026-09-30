<?php
declare(strict_types=1);

/**
 *+------------------
 * madong - 消息订阅模型
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
 * 消息订阅退订模型
 * 默认全量订阅，表中只存储"退订"记录。
 * - 无记录 → 已订阅（默认）
 * - 有记录（该 user_id + definition_id）→ 已退订
 */
class Subscribe extends BaseModel
{
    protected $table = 'sys_message_subscribe';

    protected $fillable = [
        'user_id',
        'definition_id',
        'tenant_id',
    ];

    protected $casts = [
        'definition_id' => 'string',
        'id'            => 'string',
        'user_id'       => 'string',
        'tenant_id'     => 'string',
    ];

    /**
     * 关联消息定义
     */
    public function definition(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Definition::class, 'definition_id');
    }
}
