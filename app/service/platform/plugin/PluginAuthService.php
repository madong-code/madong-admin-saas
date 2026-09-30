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

namespace app\service\platform\plugin;

use core\foundation\base\BaseService;

/**
 * 平台端授权信息服务 - 直接读取配置
 */
class PluginAuthService extends BaseService
{
    public function getAuthInfo(): array
    {
        return [
            'company_name' => config('madong.company_name', '-'),
            'domain'       => config('madong.domain', '-'),
            'auth_code'    => config('madong.auth_code', ''),
        ];
    }
}
