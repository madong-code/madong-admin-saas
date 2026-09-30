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
namespace app\event\plugin;

use core\foundation\base\BaseEvent;

/**
 * 插件更新后事件
 */
class PluginUpdated extends BaseEvent
{
    public string $code;
    public string $version;
    public array $extra;

    /**
     * @param string $code     插件编码
     * @param string $version  已更新到的版本
     * @param array  $extra    业务透传载荷
     * @param string $context  调用上下文('legacy' | 'orchestrator')
     */
    public function __construct(string $code, string $version, array $extra = [], string $context = 'legacy')
    {
        $this->code = $code;
        $this->version = $version;
        $this->extra = array_merge($extra, ['context' => $context]);
    }

    public function getEventName(): string
    {
        return 'plugin.updated';
    }
}
