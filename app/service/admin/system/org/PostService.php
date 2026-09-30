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

namespace app\service\admin\system\org;

use app\dao\system\org\PostDao;
use core\foundation\base\BaseService;

/**
 * @method save(array $data)
 */
class PostService extends BaseService
{


    public function __construct(PostDao $dao)
    {
        $this->dao = $dao;
    }

}
