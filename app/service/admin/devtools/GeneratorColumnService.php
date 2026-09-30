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


use app\dao\devtools\GeneratorColumnDao;
use core\foundation\base\BaseService;
use core\foundation\exception\handler\AdminException;
use support\Db;

class GeneratorColumnService extends BaseService
{
    public function __construct(GeneratorColumnDao $dao)
    {
        $this->dao = $dao;
    }

}