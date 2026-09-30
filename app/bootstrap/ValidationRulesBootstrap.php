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
namespace app\bootstrap;

use Webman\Bootstrap;
use Webman\Validation\Factory\ValidationFactory;

/**
 * 验证规则注册 Bootstrap
 * 在应用启动时注册自定义验证规则（如 mobile、phone）
 * 通过 Illuminate\Validation\Factory 的 extend() 方法注册
 */
class ValidationRulesBootstrap implements Bootstrap
{
    public static function start($worker): void
    {
        $factory = ValidationFactory::getFactory();

        // 注册手机号验证规则
        $factory->extend('mobile', function ($attribute, $value, $parameters) {
            return preg_match('/^1[3-9]\d{9}$/', $value);
        }, '无效的手机号码');

        // 注册手机号验证规则（别名）
        $factory->extend('phone', function ($attribute, $value, $parameters) {
            return preg_match('/^1[3-9]\d{9}$/', $value);
        }, '无效的手机号码');
    }
}
