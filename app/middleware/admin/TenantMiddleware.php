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

use app\model\tenant\Tenant;
use core\business\tenant\context\TenantContext;
use core\business\tenant\TenantConnectionManager;
use Webman\MiddlewareInterface;
use Webman\Http\Response;
use Webman\Http\Request;

/**
 * 租户中间件
 *
 * 从请求头 X-Tenant-Id 或参数 tenant_id 提取租户ID
 * 并注入到 TenantContext，后续 TenantModel 自动隔离数据
 *
 * database 模式：自动建立租户数据库连接
 */
class TenantMiddleware implements MiddlewareInterface
{
    /**
     * 不触发租户连接切换的路径前缀（登录、公共接口等）
     */
    protected array $except = [
        '/adminapi/login',
        '/auth/login',
        '/auth/login/get-captcha-open-flag',
        '/auth/login/captcha',
        '/auth/login/send-sms',
        '/auth/login/tenant-mode',
        '/auth/login/tenants',
        '/auth/login/auth/public-key',
        '/auth/login/third-party',
    ];

    /**
     * @param Request  $request
     * @param callable $next
     *
     * @return Response
     * @throws \core\foundation\exception\handler\TenantException
     */
    public function process(Request $request, callable $next): Response
    {
        // SINGLE 模式下跳过租户中间件
        if (TenantContext::isSingleMode()) {
            return $next($request);
        }

        // 每次请求先清除上一个请求残留的上下文，避免 webman 长进程中
        // static 属性跨请求泄漏（TenantContext::$tenantId, $isolationMode 等）导致
        // 后续模型连接选择错误
        TenantContext::clear();

        $tenantId = $request->header('X-Tenant-Id')
            ?? $request->input('tenant_id')
            ?? null;

        if (empty($tenantId)) {
            return $next($request);
        }

        // 登录等公共接口中 tenant_id 是登录参数，不应触发租户连接切换
        $path = ltrim($request->path(), '/');
        foreach ($this->except as $prefix) {
            $prefix = ltrim($prefix, '/');
            if ($path === $prefix || str_starts_with($path, $prefix . '/')) {
                // 清除上一个请求残留的租户上下文，避免泄漏到当前请求
                TenantContext::clear();
                return $next($request);
            }
        }

        // 注入租户上下文
        TenantContext::setTenant($tenantId);

        // 根据租户隔离模式设置连接上下文
        $tenantInfo = TenantContext::getTenantInfo();
        if ($tenantInfo) {
            $dbMode = $tenantInfo['database_mode'] ?? '';
            if ($dbMode === Tenant::MODE_DATABASE) {
                // database 模式：自动建立租户数据库连接
                TenantConnectionManager::setCurrentConnection($tenantId, false);
            } else {
                // field 模式（或其它）：仅设置上下文隔离模式，使用主库
                TenantContext::setIsolationMode('field');
            }
        }

        return $next($request);
    }
}
