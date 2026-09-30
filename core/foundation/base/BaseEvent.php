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

namespace core\foundation\base;

use core\business\tenant\context\TenantContext;
use Webman\Event\Event;

/**
 * 事件抽象基类
 *
 * 所有模块（adminapi、api、platform 等）的事件统一继承此类。
 * 自动捕获当前请求的 tenantId，支持事件链和未来异步场景。
 * 同步场景下 Listener 可直接使用 TenantContext 静态类获取租户信息。
 */
abstract class BaseEvent
{
    /**
     * 当前租户ID（dispatch 时自动捕获）
     * 非租户模式或超级管理员时为 null
     */
    public int|string|null $tenantId = null;

    /**
     * 捕获当前租户上下文
     * 非租户模式或超级管理员跳过
     */
    protected function captureTenantContext(): void
    {
        if (TenantContext::isSingleMode() || TenantContext::isSuperAdmin()) {
            return;
        }
        if (TenantContext::isInitialized()) {
            $this->tenantId = TenantContext::getTenantId();
        }
    }

    /**
     * 获取事件名称（子类必须实现）
     */
    abstract public function getEventName(): string;

    /**
     * 触发事件
     * 子类可覆写以自定义返回值（如 MenuFormattingEvent 返回 $this->result）
     */
    public function dispatch()
    {
        $this->captureTenantContext();
        Event::emit($this->getEventName(), $this);
    }
}
