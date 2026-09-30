<?php
declare(strict_types=1);

/**
 *+------------------
 * madong - 消息模板模型
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
 * 消息模板模型
 * 模板是独立实体，通过中间表 MessageDefinitionRel 与消息定义多对多关联。
 * 一套模板可被多个消息定义复用。
 * 模板类型:
 * - system: 系统内推送
 * - sms: 短信
 * - email: 邮件
 * - webhook: Webhook
 */
class Template extends BaseModel
{
    protected $table = 'sys_message_template';

    protected $fillable = [
        'id',
        'type',
        'template_id',
        'title',
        'content_template',
        'button_template',
        'url',
        'uni_url',
        'webhook_url',
        'image',
        'enabled',
        'push_rule',
        'minute',
        'is_system',
        'tenant_id',
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'id'          => 'string',
        'template_id' => 'string',
        'tenant_id'   => 'string',
        'minute'      => 'integer',
        'enabled'     => 'integer',
    ];

    /**
     * 关联的消息定义（多对多）
     */
    public function definitions(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Definition::class, DefinitionRel::class, 'template_id', 'definition_id')->using(DefinitionRel::class);
    }
}
