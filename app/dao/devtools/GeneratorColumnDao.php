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

namespace app\dao\devtools;

use app\model\devtools\GeneratorColumn;
use core\foundation\base\BaseDao;

/**
 * 代码生成器-表数据访问层
 *
 * @author Mr.April
 * @since  1.0
 */
class GeneratorColumnDao extends BaseDao
{

    protected function setModel(): string
    {
        return GeneratorColumn::class;
    }

}
