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

namespace app\dao\plugin;

use app\model\plugin\PluginLog;
use core\foundation\base\BaseDao;

/**
 * 插件日志数据访问层
 *
 * @author Mr.April
 * @since  1.0
 */
class PluginLogDao extends BaseDao
{

    protected function setModel(): string
    {
        return PluginLog::class;
    }

}
