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
namespace app\platform\validate;

/**
 * ID参数验证器
 */
class IdValidate extends BaseValidate
{
    protected array $rules = [
        'id' => 'required|string|max:64',
    ];

    protected array $messages = [
        'id.required' => 'ID不能为空',
        'id.string' => 'ID必须是字符串',
        'id.max' => 'ID长度不能超过64字符',
    ];
}
