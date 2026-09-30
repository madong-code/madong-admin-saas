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

namespace app\dao\system\admin;


use app\model\system\admin\AdminRole;
use core\foundation\base\BaseDao;

class AdminRoleDao extends BaseDao
{

    protected function setModel(): string
    {
        return AdminRole::class;
    }
}
