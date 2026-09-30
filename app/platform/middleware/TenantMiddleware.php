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
use core\foundation\exception\handler\TenantException;
use Webman\MiddlewareInterface;
use Webman\Http\Response;
use Webman\Http\Request;

/**
 * 租户中间件
 * 
 * 验证请求中包含有效的租户信息
 */
class TenantMiddleware implements MiddlewareInterface
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
        // 获取租户ID
        $tenantId = $request->header('X-Tenant-Id') 
            ?? $request->input('tenant_id') 
            ?? null;

        // 如果没有租户ID，尝试从上下文中获取
        if (empty($tenantId) && TenantContext::get()) {
            $tenantId = TenantContext::get()->id ?? null;
        }

        // 验证租户ID
        if (empty($tenantId)) {
            throw new TenantException('缺少租户标识', 401);
        }

        // 设置租户上下文
        TenantContext::set($tenantId);

        return $next($request);
    }
}
