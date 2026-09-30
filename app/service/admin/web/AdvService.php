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
namespace app\service\admin\web;

use app\dao\web\AdvDao;
use core\foundation\base\BaseService;

/**
 * 广告服务类
 */
class AdvService extends BaseService
{
    /**
     * 构造方法
     */
    public function __construct(AdvDao $dao)
    {
        $this->dao = $dao;
    }
}