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

/**
 * 框架标识配置
 *
 * 安装器接口（/install/framework、/install/mode）下发该框架标识；
 * 唯一框架，不随多租户功能开启或关闭变化，安装流程不提供框架选择。
 */
return [
    // 框架标识（默认且唯一，自动注入安装流程）
    'name' => 'madong-admin-saas',
    // 框架版本
    'version' => '5.2.0',
];
