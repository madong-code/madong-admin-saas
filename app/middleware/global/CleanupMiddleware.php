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
namespace app\middleware\global;

use core\business\tenant\context\TenantContext;
use Webman\MiddlewareInterface;
use Webman\Http\Response;
use Webman\Http\Request;

/**
 * 请求级兜底清理中间件
 *
 * Workerman 长驻进程模式下，每次请求结束后必须清理所有跨请求静态状态。
 * 本中间件以 try-finally 模式包裹整个请求生命周期，
 * 确保无论中间件链/控制器正常返回还是抛出异常，
 * TenantContext（租户上下文）都被彻底重置，
 * 避免上一个请求的租户上下文泄漏到下一个请求。
 *
 * 连接池由 Pool 自身 idle_timeout 机制自动回收，无需手动清理。
 *
 * 注册为 @ 超全局中间件（最外层），cleanup 代码在最终阶段执行。
 */
class CleanupMiddleware implements MiddlewareInterface
{
    public function process(Request $request, callable $next): Response
    {
        try {
            return $next($request);
        } finally {
            TenantContext::clear();
        }
    }
}
