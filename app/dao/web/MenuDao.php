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
namespace app\dao\web;

use app\model\web\Menu;
use core\foundation\base\BaseDao;

/**
 * 前端菜单数据访问对象
 */
class MenuDao extends BaseDao
{
    /**
     * 设置模型类
     */
    protected function setModel(): string
    {
        return Menu::class;
    }
}
