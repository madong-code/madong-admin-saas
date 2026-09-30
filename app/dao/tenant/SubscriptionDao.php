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
namespace app\dao\tenant;

use app\model\tenant\Subscription;
use core\foundation\base\BaseDao;

/**
 * 套餐订阅 DAO
 */
class SubscriptionDao extends BaseDao
{
    protected function setModel(): string
    {
        return Subscription::class;
    }
}
