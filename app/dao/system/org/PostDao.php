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
namespace app\dao\system\org;

use app\model\system\org\Post;
use core\foundation\base\BaseDao;

class PostDao extends BaseDao
{

    protected function setModel(): string
    {
        return Post::class;
    }
}
