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
namespace core\infrastructure\scheduler\event;

use app\enum\system\OperationResult;
use core\infrastructure\scheduler\event\EventBootstrap;

class SchedulingTask implements EventBootstrap
{
    /**
     * @param $crontab
     *
     * @return array
     */
    public static function parse($crontab): array
    {
        $code = OperationResult::SUCCESS->value;
        try {
            $className = $crontab['target'];
            $bizId     = $crontab['biz_id'];
            // 创建类的实例
            $instance = new $className();
            // 使用反射动态调用非静态方法
            $reflectionMethod = new \ReflectionMethod($className, 'run');
            $log              = $reflectionMethod->invokeArgs($instance, [$bizId]);
        } catch (\Throwable $throwable) {
            $code = OperationResult::FAILURE->value;
            $log  = $throwable->getMessage();
        }
        return ['log' => $log, 'code' => $code];
    }

}
