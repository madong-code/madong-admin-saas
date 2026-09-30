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

namespace app\dao\ops\logs;

use app\model\ops\logs\LoginLog;
use core\foundation\base\BaseDao;

class LoginLogDao extends BaseDao
{

    protected function setModel(): string
    {
        return LoginLog::class;
    }
}
