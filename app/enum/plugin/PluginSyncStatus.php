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

namespace app\enum\plugin;

/**
 * 插件同步状态(任务审计/租户记录共用)
 *
 * - pending   已入队,未开始
 * - running   消费中
 * - success   全部完成
 * - failed    部分或全部失败,见 error
 */
enum PluginSyncStatus: string
{
    case PENDING = 'pending';
    case RUNNING = 'running';
    case SUCCESS = 'success';
    case FAILED  = 'failed';

    /**
     * 全部合法值
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * 是否终态
     */
    public function isTerminal(): bool
    {
        return $this === self::SUCCESS || $this === self::FAILED;
    }
}
