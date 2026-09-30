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
namespace core\foundation\tool;

use app\process\Monitor;
use core\utils\Exception;
use Throwable;
use Workerman\Timer;
use Workerman\Worker;

class Util
{
    /**
     * 密码哈希
     *
     * @param        $password
     * @param string $algo
     *
     * @return false|string|null
     */
    public static function passwordHash($password, string $algo = PASSWORD_DEFAULT)
    {
        return password_hash($password, $algo);
    }

    /**
     * 验证密码哈希
     *
     * @param string $password
     * @param string $hash
     *
     * @return bool
     */
    public static function passwordVerify(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }

    /**
     * 获取语义化时间
     *
     * @param $time
     *
     * @return false|string
     */
    public static function humanDate($time)
    {
        $timestamp = is_numeric($time) ? $time : strtotime($time);
        $dur       = time() - $timestamp;
        if ($dur < 0) {
            return date('Y-m-d', $timestamp);
        } else {
            if ($dur < 60) {
                return $dur . '秒前';
            } else {
                if ($dur < 3600) {
                    return floor($dur / 60) . '分钟前';
                } else {
                    if ($dur < 86400) {
                        return floor($dur / 3600) . '小时前';
                    } else {
                        if ($dur < 2592000) { // 30天内
                            return floor($dur / 86400) . '天前';
                        } else {
                            return date('Y-m-d', $timestamp);;
                        }
                    }
                }
            }
        }
        return date('Y-m-d', $timestamp);
    }

    /**
     * 格式化文件大小
     *
     * @param $file_size
     *
     * @return string
     */
    public static function formatBytes($file_size): string
    {
        $size = sprintf("%u", $file_size);
        if ($size == 0) {
            return ("0 Bytes");
        }
        $size_name = array(" Bytes", " KB", " MB", " GB", " TB", " PB", " EB", " ZB", " YB");
        return round($size / pow(1024, ($i = floor(log($size, 1024)))), 2) . $size_name[$i];
    }

    /**
     * 数据库字符串转义
     *
     * @param $var
     *
     * @return false|string
     */
    public static function pdoQuote($var)
    {
        return Util::db()->getPdo()->quote($var);
    }

    /**
     * 检查表名是否合法
     *
     * @param string $table
     *
     * @return string
     * @throws Exception
     */
    public static function checkTableName(string $table): string
    {
        if (!preg_match('/^[a-zA-Z_0-9]+$/', $table)) {
            throw new Exception('表名不合法');
        }
        return $table;
    }

    /**
     * 变量或数组中的元素只能是字母数字下划线组合
     *
     * @param $var
     *
     * @return mixed
     * @throws Exception
     */
    public static function filterAlphaNum($var)
    {
        $vars = (array)$var;
        array_walk_recursive($vars, function ($item) {
            if (is_string($item) && !preg_match('/^[a-zA-Z_0-9]+$/', $item)) {
                throw new Exception('参数不合法');
            }
        });
        return $var;
    }

    /**
     * 变量或数组中的元素只能是字母数字
     *
     * @param $var
     *
     * @return mixed
     * @throws Exception
     */
    public static function filterNum($var)
    {
        $vars = (array)$var;
        array_walk_recursive($vars, function ($item) {
            if (is_string($item) && !preg_match('/^[0-9]+$/', $item)) {
                throw new Exception('参数不合法');
            }
        });
        return $var;
    }

    /**
     * @desc 检测是否是合法URL Path
     *
     * @param $var
     *
     * @return string
     * @throws Exception
     */
    public static function filterUrlPath($var): string
    {
        if (!is_string($var)) {
            throw new Exception('参数不合法，地址必须是一个字符串！');
        }

        if (strpos($var, 'https://') === 0 || strpos($var, 'http://') === 0) {
            if (!filter_var($var, FILTER_VALIDATE_URL)) {
                throw new Exception('参数不合法，不是合法的URL地址！');
            }
        } elseif (!preg_match('/^[a-zA-Z0-9_\-\/&?.]+$/', $var)) {
            throw new Exception('参数不合法，不是合法的Path！');
        }
        return $var;
    }

    /**
     * 检测是否是合法Path
     *
     * @param $var
     *
     * @return string
     * @throws \Exception
     */
    public static function filterPath($var): string
    {
        if (!is_string($var) || !preg_match('/^[a-zA-Z0-9_\-\/]+$/', $var)) {
            throw new \Exception('参数不合法');
        }
        return $var;
    }

    /**
     * 类转换为url path
     *
     * @param $controller_class
     *
     * @return false|string
     */
    static function controllerToUrlPath($controller_class): false|string
    {
        $key    = strtolower($controller_class);
        $action = '';
        if (strpos($key, '@')) {
            [$key, $action] = explode('@', $key, 2);
        }
        $prefix = 'plugin';
        $paths  = explode('\\', $key);
        if (count($paths) < 2) {
            return false;
        }
        $base = '';
        if (str_starts_with($key, "$prefix\\")) {
            if (count($paths) < 4) {
                return false;
            }
            array_shift($paths);
            $plugin = array_shift($paths);
            $base   = "/app/$plugin/";
        }
        array_shift($paths);
        foreach ($paths as $index => $path) {
            if ($path === 'controller') {
                unset($paths[$index]);
            }
        }
        $suffix = 'controller';
        $code   = $base . implode('/', $paths);
        if (str_ends_with($code, $suffix)) {
            $code = substr($code, 0, -strlen($suffix));
        }
        return $action ? "$code/$action" : $code;
    }

    /**
     * 转换为驼峰
     *
     * @param string $value
     *
     * @return string
     */
    public static function camel(string $value): string
    {
        static $cache = [];
        $key = $value;

        if (isset($cache[$key])) {
            return $cache[$key];
        }

        $value = ucwords(str_replace(['-', '_'], ' ', $value));

        return $cache[$key] = str_replace(' ', '', $value);
    }

    /**
     * 转换为小驼峰
     *
     * @param $value
     *
     * @return string
     */
    public static function smCamel($value): string
    {
        return lcfirst(static::camel($value));
    }

    /**
     * 获取某个composer包的版本
     *
     * @param string $package
     *
     * @return mixed|string
     */
    public static function getPackageVersion(string $package): mixed
    {
        $installed_php = base_path('vendor/composer/installed.php');
        if (is_file($installed_php)) {
            $packages = include $installed_php;
        }
        return substr($packages['versions'][$package]['version'] ?? 'unknown  ', 0, -2);
    }

    /**
     * Reload webman
     *
     * @return bool
     */
    public static function reloadWebman(): bool
    {
        if (function_exists('posix_kill')) {
            try {
                posix_kill(posix_getppid(), SIGUSR1);
                return true;
            } catch (Throwable $e) {
            }
        } else {
            Timer::add(1, function () {
                Worker::stopAll();
            });
        }
        return false;
    }

    /**
     * Pause file monitor
     *
     * @return void
     */
    public static function pauseFileMonitor(): void
    {
        if (method_exists(Monitor::class, 'pause')) {
            Monitor::pause();
        }
    }

    /**
     * Resume file monitor
     *
     * @return void
     */
    public static function resumeFileMonitor(): void
    {
        if (method_exists(Monitor::class, 'resume')) {
            Monitor::resume();
        }
    }



     /**
     * 加载指定插件的所有菜单位置
     *
     * @param string $pluginName 插件名称
     * @param array  $menuTypes  菜单类型列表，默认加载admin和site
     *
     * @return array 插件的菜单位置配置
     */
    public static function loadPluginMenus(string $pluginName, array $menuTypes = ['admin', 'site']): array
    {
        $menus = [];
        $pluginPath = base_path('plugin' . DIRECTORY_SEPARATOR);
        foreach ($menuTypes as $menuType) {
            $menuFilePath = $pluginPath . $pluginName . DIRECTORY_SEPARATOR
                          . 'resource' . DIRECTORY_SEPARATOR
                          . 'menu' . DIRECTORY_SEPARATOR
                          . $menuType . '.php';

            if (is_file($menuFilePath)) {
                try {
                    $menuConfig = include $menuFilePath;
                    if (is_array($menuConfig)) {
                        $menus[$menuType] = $menuConfig;
                    }
                } catch (\Throwable $e) {
                    // 记录错误但不中断程序

                }
            }
        }

        return $menus;
    }

    /**
     * 加载指定插件的特定类型菜单
     *
     * @param string $pluginName 插件名称
     * @param string $menuType   菜单类型（admin 或 site）
     *
     * @return array 插件的菜单位置配置
     */
    public static function loadPluginMenu(string $pluginName, string $menuType): array
    {
        $pluginPath = base_path('plugin' . DIRECTORY_SEPARATOR);
        $menuFilePath = $pluginPath . $pluginName . DIRECTORY_SEPARATOR
                      . 'resource' . DIRECTORY_SEPARATOR
                      . 'menu' . DIRECTORY_SEPARATOR
                      . $menuType . '.php';

        if (!is_file($menuFilePath)) {
            return [];
        }

        try {
            $menuConfig = include $menuFilePath;
            return is_array($menuConfig) ? $menuConfig : [];
        } catch (\Throwable $e) {
            // 记录错误但不中断程序
            return [];
        }
    }

}


