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

namespace core\business\install\traits;

use core\business\message\MessageDataSync;

/**
 * 消息数据导入 Trait
 *
 * 安装时导入框架消息分类/定义/模板（resource/data/message/category.php，source=system）。
 * 插件消息数据由各插件在安装时按 source=plugin:{name} 自行导入。
 */
trait MessageTrait
{
    /**
     * 运行消息种子
     */
    public function runMessage(): void
    {
        $dataFile = base_path('resource/data/message/category.php');
        if (!is_file($dataFile)) {
            return;
        }

        $categories = require $dataFile;
        if (!is_array($categories)) {
            return;
        }

        (new MessageDataSync('system'))->sync($categories, MessageDataSync::MODE_SYNC);
    }
}
