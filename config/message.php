<?php
/**
 *+------------------
 * madong - 消息模块运行期配置
 *+------------------
 * Copyright (c) https://gitee.com/motion-code  All rights reserved.
 *+------------------
 * Author: Mr. April (405784684@qq.com)
 *+------------------
 * Official Website: https://madong.tech
 *
 * 分类和模块定义已移至 resource/data/message/*.php，
 * 系统安装时由 MessageDataTrait 一次性导入数据库。
 * 插件可在 plugin/{name}/resource/data/message/*.php 中定义自己的分类和模块，
 * 插件安装时由 PluginInstall::importMessageData() 自动导入。
 */

return [

    'sse' => [
        'enabled'            => true,
        'heartbeat_interval' => 30,
        'reconnect_interval' => 5,
        'max_reconnect'      => 10,
    ],

    'defaults' => [
        'expire_days'   => 30,
        'page_size'     => 15,
        'max_page_size' => 100,
    ],
];
