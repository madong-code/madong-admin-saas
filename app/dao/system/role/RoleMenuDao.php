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

namespace app\dao\system\role;


use app\model\system\role\RoleMenu;
use core\foundation\base\BaseDao;

/**
 *
 * 角色菜单
 * @author Mr.April
 * @since  1.0
 */
class RoleMenuDao extends BaseDao
{

    protected function setModel(): string
    {
        return RoleMenu::class;
    }
}
