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
 * Official Website: http://www.madong.cn
 */

namespace app\model\system\role;

use core\foundation\base\BasePivot;

/**
 * 关联模型
 *
 * @author Mr.April
 * @since  1.0
 */
class RoleDept extends BasePivot
{
    protected $table = 'sys_role_dept';

    protected $fillable = [
        'role_id',
        'dept_id',
    ];

    protected $casts = [
        'dept_id'   => 'string',
        'role_id'   => 'string',
        'tenant_id' => 'string',
    ];
}
