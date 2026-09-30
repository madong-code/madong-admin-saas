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

namespace app\adminapi\validate\system\role;

use core\foundation\base\BaseValidate;

class RoleValidate extends BaseValidate
{
    /**
     * 定义验证规则
     */
    protected array $rules = [
        'id'         => 'required',
        'code'       => 'required|alpha',
        'name'       => 'required|max:16',
        'data_scope' => 'required',
    ];

    /**
     * 定义错误信息
     */
    protected array $messages = [
        'id.required'         => '参数id不能为空',
        'code.required'       => '角色标识必须填写',
        'code.alpha'          => '角色标识只能由英文字母组成',
        'name.required'       => '角色名称必须填写',
        'name.max'            => '角色名称最多不能超过16个字符',
        'data_scope.required' => '数据权限不能为空',
    ];

    /**
     * 定义场景
     */
    protected array $scenes = [
        'store'      => [
            'code',
            'name',
        ],
        'update'     => [
            'id',
            'code',
            'name',
        ],
        'data-scope' => [
            'id',
            'data_scope',
        ],
    ];
}
