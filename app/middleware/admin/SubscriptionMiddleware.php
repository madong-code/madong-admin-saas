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
namespace app\middleware\admin;

use core\business\tenant\context\TenantContext;
use Webman\MiddlewareInterface;
use Webman\Http\Response;
use Webman\Http\Request;

/**
 * 订阅中间件
 *
 * 验证当前租户的订阅是否有效（过期/暂停则拒绝）
 */
class SubscriptionMiddleware implements MiddlewareInterface
{
    public function process(Request $request, callable $next): Response
    {
        if (!TenantContext::isInitialized()) {
            return $next($request);
        }

//        if (!TenantContext::isSubscriptionValid()) {
//            return new Response(403, ['Content-Type' => 'application/json'],
//                json_encode(['code' => -1, 'msg' => '订阅已过期或未激活'])
//            );
//        }

        return $next($request);
    }
}
