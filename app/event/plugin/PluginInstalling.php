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
 * 插件安装前事件
 */
class PluginInstalling extends BaseEvent
{
    public string $code;
    public string $version;
    public array $extra;

    /**
     * @param string $code     插件编码
     * @param string $version  目标版本
     * @param array  $extra    业务透传载荷(将合并到事件 extra)
     * @param string $context  调用上下文标识:
     *                         - 'legacy'        旧直调路径(默认,触发 TenantPluginSyncListener 自动同步)
     *                         - 'orchestrator'  PluginLifecycleOrchestrator 编排入口(已显式处理扇出,Listener 需幂等跳过)
     */
    public function __construct(string $code, string $version, array $extra = [], string $context = 'legacy')
    {
        $this->code = $code;
        $this->version = $version;
        $this->extra = array_merge($extra, ['context' => $context]);
    }

    public function getEventName(): string
    {
        return 'plugin.installing';
    }
}
