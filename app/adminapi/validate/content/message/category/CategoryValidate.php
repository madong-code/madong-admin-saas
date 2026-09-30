<?php
declare(strict_types=1);

/**
 *+------------------
 * madong - 消息分类验证器
 *+------------------
 * Copyright (c) https://gitee.com/motion-code  All rights reserved.
 *+------------------
 * Author: Mr. April (405784684@qq.com)
 *+------------------
 * Official Website: https://madong.tech
 */

namespace app\adminapi\validate\content\message\category;

use core\foundation\base\BaseValidate;

class CategoryValidate extends BaseValidate
{
    protected array $rules = [
        'id'          => 'required',
        'key'         => 'required',
        'name'        => 'required',
    ];

    protected array $messages = [
        'id.required'   => '参数错误缺少id',
        'key.required'  => '分类标识必须填写',
        'name.required' => '分类名称必须填写',
    ];

    protected array $scenes = [
        'store'  => [
            'key',
            'name',
        ],
        'update' => [
            'id',
            'key',
            'name',
        ],
    ];
}
