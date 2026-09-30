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
namespace app\service\admin\member;

use app\dao\member\MemberDao;
use app\dao\member\MemberLevelDao;
use core\foundation\base\BaseService;
use core\foundation\exception\handler\AdminException;

/**
 * 会员等级服务类
 */
class MemberLevelService extends BaseService
{

    /**
     * 构造方法
     */
    public function __construct(MemberLevelDao $dao)
    {
        $this->dao = $dao;
    }

}