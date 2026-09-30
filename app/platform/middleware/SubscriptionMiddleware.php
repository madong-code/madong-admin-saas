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
namespace app\platform\middleware;

use app\context\TenantContext;
use core\foundation\exception\handler\SubscriptionException;
use Webman\MiddlewareInterface;
use Webman\Http\Response;
use Webman\Http\Request;

/**
 * 订阅中间件
 * 
 * 验证当前租户的订阅状态
 */
class SubscriptionMiddleware implements MiddlewareInterface
{
    /**
     * 中间件处理方法
     *
     * @param Request $request
     * @param callable $next
     * @return Response
     */
    public function process(Request $request, callable $next): Response
    {
        // 获取当前租户
        $tenant = TenantContext::get();

        if (empty($tenant)) {
            throw new SubscriptionException('租户上下文不存在', 401);
        }

        // 检查订阅状态
        if ($tenant->getAttribute('subscription_status') === 0) {
            throw new SubscriptionException('订阅已过期或未激活', 403);
        }

        return $next($request);
    }
}
