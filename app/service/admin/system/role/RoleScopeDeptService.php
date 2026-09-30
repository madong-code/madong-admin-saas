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

namespace app\service\admin\system\role;

use app\dao\system\role\RoleScopeDeptDao;
use core\foundation\base\BaseService;

/**
 * @method getColumn(int[]|string[] $array, string $string)
 */
class RoleScopeDeptService extends BaseService
{

    public function __construct(RoleScopeDeptDao $dao)
    {
        $this->dao = $dao;
    }

}
