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

use core\infrastructure\logger\Logger;

/**
 * 监听器抽象基类
 *
 * 所有模块（adminapi、api、platform 等）的监听器统一继承此类。
 * 模板方法模式，final handle() 统一处理错误日志。
 * 同步场景下不处理租户上下文，由 TenantContext 静态类保证。
 * 参照 core\foundation\base\BaseQueueConsumer 的设计风格。
 */
abstract class BaseListener
{
    /**
     * 模板方法：统一 try/catch + 日志
     * final 禁止子类覆写，业务逻辑实现在 process() 中
     */
    final public function handle($event): void
    {
        try {
            $this->process($event);
        } catch (\Throwable $e) {
            Logger::error('[' . static::class . '] 处理失败', [
                'error' => $e->getMessage(),
                'event' => get_class($event),
                'file'  => $e->getFile(),
                'line'  => $e->getLine(),
            ]);
            throw $e;
        }
    }

    /**
     * 业务逻辑处理（子类必须实现）
     */
    abstract protected function process($event): void;
}
