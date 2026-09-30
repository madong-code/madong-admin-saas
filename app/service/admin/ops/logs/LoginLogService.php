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

namespace app\service\admin\ops\logs;

use app\dao\ops\logs\LoginLogDao;
use core\foundation\base\BaseService;

/**
 * @method save(array $data)
 */
class LoginLogService extends BaseService
{

    public function __construct(LoginLogDao $dao)
    {
        $this->dao = $dao;
    }

}
