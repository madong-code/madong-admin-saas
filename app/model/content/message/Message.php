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
 * Official Website: https://madong.tech
 */

namespace app\model\content\message;

use app\model\system\admin\Admin;
use core\foundation\base\BaseModel;

/**
 * 消息记录模型
 *
 * 实际发送给用户的消息记录。
 * definition_id 关联到消息定义（获取导航信息等）。
 * category_id 为冗余字段，便于快速按分类聚合统计未读数。
 *
 * @author Mr.April
 * @since  1.0
 */
class Message extends BaseModel
{

    protected $table = 'sys_message';

    /**
     * 指示是否自动维护时间戳
     *
     * @var bool
     */
    public $timestamps = true;

    protected $appends = ['created_date', 'updated_date'];

    protected $fillable = [
        'id',
        'definition_id',
        'category_id',
        'title',
        'content',
        'sender_id',
        'receiver_id',
        'status',
        'priority',
        'channel',
        'related_id',
        'related_type',
        'action_url',
        'action_params',
        'extra_data',
        'message_uuid',
        'read_at',
        'created_at',
        'updated_at',
        'expired_at',
        'tenant_id',
    ];


    protected $casts = [
        'category_id'   => 'string',
        'definition_id' => 'string',
        'id'            => 'string',
        'receiver_id'   => 'string',
        'related_id'    => 'string',
        'sender_id'     => 'string',
        'tenant_id'     => 'string',
    ];

    /**
     * 关联发送用户
     */
    public function sender(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Admin::class, 'id', 'sender_id')->select(['id', 'real_name', 'user_name', 'dept_id']);
    }

    /**
     * 关联分类（冗余，快速查询）
     */
    public function category(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    /**
     * 关联消息定义
     */
    public function definition(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Definition::class, 'definition_id');
    }
}
