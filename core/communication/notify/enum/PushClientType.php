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
namespace core\communication\notify\enum;

use core\foundation\interface\IEnum;

/**
 * 推送客户端枚举
 *
 * @author Mr.April
 * @since  1.0
 */
enum PushClientType: string implements IEnum
{

    case BACKEND = 'backend';  // 管理后台
    case APP = 'app';          // 移动应用
    case MP = 'mp';            // 小程序
    case WEB = 'web';          // 网页端

    /**
     * 获取人类识别的标签
     *
     * @return string
     */
    public function label(): string
    {
        return match ($this) {
            self::BACKEND => '管理后台',
            self::APP => '移动应用',
            self::MP => '小程序',
            self::WEB => '网页端',
        };
    }

    /**
     * 获取所有枚举值
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
