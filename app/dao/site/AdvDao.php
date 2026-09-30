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
namespace app\dao\site;

use app\model\web\Adv;
use core\foundation\base\BaseDao;

/**
 * 广告数据访问对象
 */
class AdvDao extends BaseDao
{
    /**
     * 设置模型类
     */
    protected function setModel(): string
    {
        return Adv::class;
    }
}
