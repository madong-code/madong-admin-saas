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
namespace core\security\jwt\enum;

/**
 * 客户端类型枚举
 */
enum ClientType: string
{
    case ADMIN = 'admin';
    case API = 'api';
    case WEB = 'web';
    case MOBILE = 'mobile';
    case MINI = 'mini';
    case PLATFORM = 'platform';

    /**
     * 检查客户端类型是否有效
     */
    public static function isValid(string $type): bool
    {
        foreach (self::cases() as $case) {
            if ($case->value === $type) {
                return true;
            }
        }
        // 合并配置文件中定义的额外类型
        $config = config('jwt.app.client_types', []);
        return in_array($type, $config, true);
    }

    /**
     * 获取所有有效的客户端类型值
     *
     * @return string[]
     */
    public static function values(): array
    {
        $values = array_map(fn(self $case) => $case->value, self::cases());
        
        // 合并配置文件中定义的额外类型
        $config = config('jwt.app.client_types', []);
        return array_merge($values, $config);
    }

    /**
     * 获取枚举值
     */
    public function value(): string
    {
        return $this->value;
    }
}
