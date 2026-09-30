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

namespace app\dao\content\review;

use app\model\content\review\ReviewLog;
use core\foundation\base\BaseDao;

/**
 * 审核操作日志DAO
 */
class ReviewLogDao extends BaseDao
{
    protected function setModel(): string
    {
        return ReviewLog::class;
    }
}
