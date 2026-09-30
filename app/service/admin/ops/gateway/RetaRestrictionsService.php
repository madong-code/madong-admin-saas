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

namespace app\service\admin\ops\gateway;

use app\dao\ops\gateway\RateRestrictionsDao;
use core\foundation\base\BaseService;

class RetaRestrictionsService extends BaseService
{

    public function __construct(RateRestrictionsDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 缓存key
     */
    const CACHE_KEY = 'rate_restrictions';

}
