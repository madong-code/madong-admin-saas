<?php
declare(strict_types=1);

/**
 *+------------------
 * madong - 消息模板验证器
 *+------------------
 * Copyright (c) https://gitee.com/motion-code  All rights reserved.
 *+------------------
 * Author: Mr. April (405784684@qq.com)
 *+------------------
 * Official Website: https://madong.tech
 */

namespace app\adminapi\validate\content\message\template;

use core\foundation\base\BaseValidate;

class TemplateValidate extends BaseValidate
{
    protected array $rules = [
        'id'              => 'required',
        'type'            => 'required',
        'template_id'     => 'max:100',
        'title'           => 'max:200',
        'content_template' => 'max:65535',
        'button_template' => 'max:500',
        'url'             => 'max:500',
        'uni_url'         => 'max:500',
        'webhook_url'     => 'max:500',
        'image'           => 'max:255',
        'enabled'         => 'in:0,1',
        'push_rule'       => 'in:0,1',
        'minute'          => 'integer|min:0|required_if:push_rule,1',
    ];

    protected array $messages = [
        'id.required'          => '参数错误缺少id',
        'type.required'        => '模板类型必须选择',
        'enabled.in'           => '启用状态取值错误',
        'push_rule.in'         => '推送规则取值错误，仅支持立即推送或延迟推送',
        'minute.required_if'   => '延迟推送时请填写延迟分钟数',
        'minute.min'           => '延迟分钟数不能小于0',
    ];

    protected array $scenes = [
        'store' => [
            'type',
            'enabled',
            'push_rule',
            'minute',
        ],
        'update' => [
            'type',
            'enabled',
            'push_rule',
            'minute',
        ],
    ];
}
