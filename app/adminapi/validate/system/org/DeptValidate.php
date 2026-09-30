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

namespace app\adminapi\validate\system\org;

use core\foundation\base\BaseValidate;

class DeptValidate extends BaseValidate
{
    /**
     * 定义验证规则
     */
    protected array $rules = [
        'code'    => 'required|alphaDash',
        'name'    => 'required|max:16',
        'enabled' => 'required',
    ];

    /**
     * 定义错误信息
     */
    protected array $messages = [
        'code.required'   => '部门标识必须填写',
        'code.alphaDash'  => '部门标识只能由英文字母组成',
        'name.required'   => '部门名称必须填写',
        'name.max'        => '部门名称最多不能超过16个字符',
        'enabled'          => '状态必须填写',
    ];

    /**
     * 定义场景
     */
    protected array $scenes = [
        'store'  => [
            'code',
            'name',
            'enabled',
        ],
        'update' => [
            'code',
            'name',
            'enabled',
        ],
    ];

}
