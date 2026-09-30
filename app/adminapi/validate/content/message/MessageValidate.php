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

namespace app\adminapi\validate\content\message;

use core\foundation\base\BaseValidate;

class MessageValidate extends BaseValidate
{
    /**
     * 定义验证规则
     */
    protected array $rules = [
        'id'      => 'required',
        'title'   => 'required',
        'content' => 'required',
        'status'  => 'required',
    ];

    /**
     * 定义错误信息
     */
    protected array $messages = [
        'id.required'      => '参数错误缺少id',
        'title.required'   => '公告名称必须填写',
        'content.required' => '公共内容不能为空',
        'status'           => '状态必须填写',
    ];

    /**
     * 定义场景
     */
    protected array $scenes = [
        'store'  => [
            'title',
            'type',
            'content',
        ],
        'update' => [
            'id',
            'title',
            'type',
            'content',
        ],
        'update-read'=>[
            'id',
            'status'
        ]
    ];

}
