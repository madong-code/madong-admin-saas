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

namespace app\model\system\org;

use core\foundation\base\BasePivot;

/**
 * 关联模型
 *
 * @author Mr.April
 * @since  1.0
 */
class DeptLeader extends BasePivot
{
    protected $table = 'sys_dept_leader';

    protected $fillable = [
        'dept_id',
        'admin_id',
    ];

    protected $casts = [
        'admin_id'  => 'string',
        'dept_id'   => 'string',
        'tenant_id' => 'string',
    ];
}
