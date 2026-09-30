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

namespace app\adminapi\validate\system\dict;

use core\foundation\base\BaseValidate;

class DictValidate extends BaseValidate
{
    /**
     * 定义验证规则
     */
    protected array $rules = [
        'name'      => 'required|max:16',
        'code'      => 'required|alphaDash',
        'enabled'   => 'required',
        'data_type' => 'required',
        'sort'      => 'numeric',
    ];

    /**
     * 定义错误信息
     */
    protected array $messages = [
        'name.required'      => '字典名称必须填写',
        'name.max'           => '字典名称最多不能超过16个字符',
        'code.required'      => '字典标识必须填写',
        'code.alphaDash'     => '字典标识只能由英文字母组成',
        'data_type.required' => '字典数据类型必须填写',
        'enabled'            => '状态必须填写',
        'sort.numeric'       => '排序只能是数字',
    ];

    /**
     * 定义场景
     */
    protected array $scenes = [
        'store'  => [
            'name',
            'code',
            'enabled',
            'data_type',
            'sort',
        ],
        'update' => [
            'name',
            'code',
            'data_type',
            'enabled',
            'sort',
        ],
    ];

}
