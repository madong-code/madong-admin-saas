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

class ShellTask implements EventBootstrap
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
            $target = $crontab['target'] ?? '';
            // 透传租户上下文：以环境变量形式注入脚本执行环境
            $tenantId = $crontab['tenant_id'] ?? '';
            $envPrefix = $tenantId !== '' ? 'TENANT_ID=' . escapeshellarg((string) $tenantId) . ' ' : '';
            $log = shell_exec($envPrefix . $target);
        } catch (\Throwable $e) {
            $code = OperationResult::FAILURE->value;
            $log  = $e->getMessage();
        }
        return ['code' => $code, 'log' => $log];
    }

}
