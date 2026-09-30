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
 * Official Website: https://madong.tech
 */

namespace app\dao\content\message;

use app\model\content\message\Category;
use core\foundation\base\BaseDao;

class MessageCategoryDao extends BaseDao
{
    protected function setModel(): string
    {
        return Category::class;
    }
}
