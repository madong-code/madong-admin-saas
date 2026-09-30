<?php
declare(strict_types=1);

/**
 *+------------------
 * madong - 消息管理验证器
 *+------------------
 * Copyright (c) https://gitee.com/motion-code  All rights reserved.
 *+------------------
 * Author: Mr. April (405784684@qq.com)
 *+------------------
 * Official Website: https://madong.tech
 */

namespace app\adminapi\validate\content\message\manage;

use core\foundation\base\BaseValidate;

class ManageValidate extends BaseValidate
{
    protected array $rules = [
        'id'              => 'required',
        'category_id'     => 'required',
        'key'             => 'required',
        'name'            => 'required',
    ];

    protected array $messages = [
        'id.required'          => '参数错误缺少id',
        'category_id.required' => '所属分类必须选择',
        'key.required'         => '消息标识必须填写',
        'name.required'        => '消息名称必须填写',
    ];

    protected array $scenes = [
        'store'  => [
            'category_id',
            'key',
            'name',
        ],
        'update' => [
            'id',
            'category_id',
            'key',
            'name',
        ],
    ];
}
