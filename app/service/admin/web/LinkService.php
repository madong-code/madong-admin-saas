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

use app\dao\site\LinkDao;
use core\foundation\base\BaseService;

/**
 * 友情链接服务类
 */
class LinkService extends BaseService
{
    /**
     * 构造方法
     */
    public function __construct(LinkDao $dao)
    {
        $this->dao = $dao;
    }
}
