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

namespace app\dao\system\dict;

use app\model\system\dict\DictItem;
use core\foundation\base\BaseDao;

/**
 * 字典项
 *
 * @author Mr.April
 * @since  1.0
 */
class DictItemDao extends BaseDao
{

    protected function setModel(): string
    {
        return DictItem::class;
    }

}
