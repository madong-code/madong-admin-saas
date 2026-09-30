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
namespace app\dao\tenant;

use app\model\tenant\TenantMigration;
use core\foundation\base\BaseDao;

class TenantMigrationDao extends BaseDao
{
    protected function setModel(): string
    {
        return TenantMigration::class;
    }
}
