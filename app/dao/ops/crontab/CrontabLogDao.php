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

namespace app\dao\ops\crontab;


use app\model\ops\crontab\CrontabLog;
use core\foundation\base\BaseDao;

class CrontabLogDao extends BaseDao
{

    protected function setModel(): string
    {
        return CrontabLog::class;
    }
}
