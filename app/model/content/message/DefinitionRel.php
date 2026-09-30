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
namespace app\model\content\message;

use core\foundation\base\BasePivot;

/**
 * 消息定义-模板关联模型
 * 通过中间表实现定义与模板的多对多关系
 */
class DefinitionRel extends BasePivot
{
    protected $table = 'sys_message_definition_template';

    protected $fillable = [
        'definition_id',
        'template_id',
        'tenant_id',
    ];

    protected $casts = [
        'definition_id' => 'string',
        'template_id'   => 'string',
        'tenant_id'     => 'string',
    ];
}
