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

namespace app\model\system\admin;

use core\foundation\base\BasePivot;

/**
 * 用户-关联角色模型
 *
 * @author Mr.April
 * @since  1.0
 */
class AdminRole extends BasePivot
{
    protected $table = 'sys_admin_role';

    protected $fillable = [
        'admin_id',
        'role_id',
    ];

    protected $casts = [
        'admin_id'  => 'string',
        'role_id'   => 'string',
        'tenant_id' => 'string',
    ];
}
