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
namespace core\infrastructure\logger;

use Throwable;

/**
 * 日志处理门面
 * @method static void emergency(string $name, $arguments) 系统不可用（最高级别）
 * @method static void alert(string $name, $arguments) 需立即处理
 * @method static void critical(string $name, $arguments) 严重故障
 * @method static void error(string $name, $arguments) 运行错误
 * @method static void warning(string $name, $arguments) 警告信息
 * @method static void notice(string $name, $arguments) 重要事件
 * @method static void info(string $name, $arguments) 一般事件
 * @method static void debug(string $name, $arguments) 调试信息
 * @method static void log(string $name, $arguments) 通用日志（默认级别）
 * @method static void cleanup(bool $forceAll = false, ?string $channel = null, bool $removeEmptyDirs = true) 清除日志
 *
 * @author Mr.April
 * @since  1.0
 */
final class Logger
{
    private static ?LoggerService $service = null;

    private function __construct()
    {
        // 禁止实例化
    }

    private static function getService(): LoggerService
    {
        if (self::$service === null) {
            self::$service = new LoggerService();
        }
        return self::$service;
    }

    public static function __callStatic(string $name, array $arguments): void
    {
        try {
            self::getService()->handleLog($name, $arguments);
        } catch (Throwable $e) {
            error_log('Logging failed: ' . $e->getMessage());
        }
    }
}
