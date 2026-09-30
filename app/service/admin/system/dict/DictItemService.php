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

namespace app\service\admin\system\dict;

use app\dao\system\dict\DictItemDao;
use core\foundation\base\BaseService;

/**
 * 数据字段服务
 *
 * @author Mr.April
 * @since  1.0
 */
class DictItemService extends BaseService
{

    public function __construct(DictItemDao $dao)
    {
        $this->dao = $dao;
    }

}
