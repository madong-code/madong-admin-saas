<?php
declare(strict_types=1);

/**
 * PHPUnit 最小引导：只加载 Composer 自动加载与测试辅助类，
 * 不启动 Webman 进程、不连接 MySQL/Redis，保证安全回归用例可独立运行。
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/Support/TestJwtToken.php';

if (!function_exists('config')) {
    function config(string $key, mixed $default = null): mixed
    {
        return $default;
    }
}
