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

namespace app\adminapi\validate\system\admin;

use core\foundation\base\BaseValidate;

class AuthValidate extends BaseValidate
{
    /**
     * 定义验证规则
     */
    protected array $rules = [
    ];

    /**
     * 定义错误信息
     */
    protected array $messages = [
    ];

    /**
     * 定义场景
     */
    protected array $scenes = [
        'store'  => [

        ],
        'update' => [

        ],
    ];
}
