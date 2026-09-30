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

namespace app\enum\system;

use core\foundation\interface\IEnum;

/**
 * 业务终端枚举
 */
enum BusinessPlatform: string implements IEnum
{
    case ADMIN = 'admin';    // 管理后台
    case WEB = 'web';        // Web网站
    case API = 'api';        // API服务
    case MOBILE = 'mobile';  // 移动端

    /**
     * 获取终端描述
     */
    public function label(): string
    {
        return match ($this) {
            self::ADMIN => '管理后台',
            self::WEB => 'Web网站',
            self::API => 'API服务',
            self::MOBILE => '移动端',
        };
    }

    /**
     * 获取颜色标识
     *
     * @return string
     */
    public function color(): string
    {
        return match ($this) {
            self::ADMIN => 'blue',
            self::WEB => 'pink',
            self::API => 'cyan',
            self::MOBILE => 'purple',
        };
    }
}
