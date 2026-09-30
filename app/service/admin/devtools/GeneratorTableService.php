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

namespace app\service\admin\devtools;

use app\dao\devtools\GeneratorTableDao;
use core\foundation\base\BaseService;
use core\foundation\exception\handler\AdminException;
use support\Db;

class GeneratorTableService extends BaseService
{
    public function __construct(GeneratorTableDao $dao)
    {
        $this->dao = $dao;
    }

}