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
 * Here is your custom functions.
 */

use app\model\system\admin\Admin;


/**
 * 获取时间 N分钟前
 *
 * @param     $time
 * @param int $type 1 N分钟前  2 N分钟后
 *
 * @return false|string
 */
function getDateText($time = NULL, int $type = 1): bool|string
{
    if (!is_numeric($time)) {
        $time = strtotime($time);
    }
    $text = '';
    $time = $time === NULL || $time > time() ? time() : intval($time);
    $t    = time() - $time; //时间差 （秒）
    $y    = date('Y', $time) - date('Y', time());//是否跨年

    $ext = '前';
    switch ($t) {
        case $t <= 0:
            $text = '刚刚';
            break;
        case $t < 60:
            $text = $t . '秒' . $ext; // 一分钟内
            break;
        case $t < 60 * 60:
            $text = floor($t / 60) . '分钟' . $ext; //一小时内
            break;
        case $t < 60 * 60 * 24:
            $text = floor($t / (60 * 60)) . '小时' . $ext; // 一天内
            break;
        case $t < 60 * 60 * 24 * 3:
            $text = floor($time / (60 * 60 * 24)) == 1 ? '昨天 ' . date('H:i', $time) : '前天 ' . date('H:i', $time); //昨天和前天
            break;
        case $t < 60 * 60 * 24 * 30:
            $text = date('m月d日 H:i', $time); //一个月内
            break;
        case $t < 60 * 60 * 24 * 365 && $y == 0:
            $text = date('m月d日', $time); //一年内
            break;
        default:
            $text = date('Y年m月d日', $time); //一年以前
            break;
    }
    return $text;
}

/**
 * 根据字节计算大小
 *
 * @param string|int $bytes
 *
 * @return string
 */
function formatBytes(string|int $bytes): string
{
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    for ($i = 0; $bytes > 1024; $i++) {
        $bytes /= 1024;
    }
    return round($bytes, 2) . ' ' . $units[$i];
}

/**
 * 筛选对应前缀数组或对象-数组集合
 *
 * @param array|object $input
 * @param string       $prefix
 *
 * @return array
 */
function filterByPrefix(array|object $input, string $prefix = 'f_'): array
{
    $result = [];
    if (is_array($input)) {
        foreach ($input as $key => $value) {
            if (strpos($key, $prefix) === 0) {
                $newKey          = substr($key, strlen($prefix));
                $result[$newKey] = $value;
            }
        }
    } elseif (is_object($input)) {
        foreach ($input as $key => $value) {
            if (strpos($key, $prefix) === 0) {
                $newKey          = substr($key, strlen($prefix));
                $result[$newKey] = $value;
            }
        }
    }
    return $result;
}

/**
 * 通过给定的key 构建数据
 *
 * @param       $keys
 * @param       $data
 * @param array $skipKeys
 *
 * @return mixed
 */
function ensureKeys($keys, $data, $skipKeys = []): array
{
    $result = [];
    foreach ($keys as $key) {
        if (in_array($key, $skipKeys)) {
            continue; // 跳过指定的键
        }
        $result[$key] = array_key_exists($key, $data) ? $data[$key] : "";
    }
    return $result;
}


