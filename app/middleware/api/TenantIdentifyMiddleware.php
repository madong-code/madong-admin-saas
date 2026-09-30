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
namespace app\middleware\api;

use app\model\tenant\Tenant;
use core\business\tenant\context\TenantContext;
use core\business\tenant\TenantConnectionManager;
use core\foundation\exception\handler\TenantException;
use Webman\MiddlewareInterface;
use Webman\Http\Response;
use Webman\Http\Request;

/**
 * 前端 API 租户识别中间件
 *
 * 按以下优先级识别当前租户：
 * 1. 请求头 X-Tenant-Id
 * 2. 请求参数 tenant_id
 * 3. 请求域名（通过 Tenant::findByDomain 自动匹配）
 *
 * 仅多租户模式下生效，单体模式直接跳过。
 * 多租户模式下无法识别租户时将抛出 TenantException。
 * 用于 api 路由组，为租户级站点开关等中间件提供上下文。
 *
 * @author Mr.April
 * @since  1.0
 */
class TenantIdentifyMiddleware implements MiddlewareInterface
{
    public function process(Request $request, callable $next): Response
    {
        // 单体模式下跳过
        if (TenantContext::isSingleMode()) {
            return $next($request);
        }

        // 多租户模式下，必须识别出当前请求所属的租户
        $tenantId = $this->resolveTenantId($request);

        // 开发环境下自动回退到第一个激活的租户
        if (empty($tenantId) && config('app.debug', false)) {
            $firstTenant = Tenant::where('status', Tenant::STATUS_ACTIVE)->orderBy('id')->first();
            if ($firstTenant) {
                $tenantId = $firstTenant->id;
            }
        }

        if (empty($tenantId)) {
            throw new TenantException('无法识别租户', ['host' => $request->host()], 401);
        }

        // 注入租户上下文
        TenantContext::setTenant($tenantId);

        // 根据租户隔离模式设置连接上下文
        $tenantInfo = TenantContext::getTenantInfo();
        if ($tenantInfo) {
            $dbMode = $tenantInfo['database_mode'] ?? '';
            if ($dbMode === Tenant::MODE_DATABASE) {
                TenantConnectionManager::setCurrentConnection($tenantId, false);
            } else {
                TenantContext::setIsolationMode('field');
            }
        }

        return $next($request);
    }

    /**
     * 按优先级依次尝试各识别方式
     *
     * @return string|null 识别到的租户ID，未识别到返回 null
     */
    private function resolveTenantId(Request $request): ?string
    {
        // 优先级 1&2: 请求头 / 请求参数
        $tenantId = $request->header('X-Tenant-Id')
            ?? $request->input('tenant_id')
            ?? null;

        if (!empty($tenantId)) {
            return $tenantId;
        }

        // 优先级 3: 请求域名识别
        return $this->identifyByDomain($request);
    }

    /**
     * 通过请求域名识别租户
     *
     * 从请求 Host 中提取域名，与 saas_tenant 表的 domain 字段匹配。
     * 需在 config/tenant.php 中启用 features.multi_domain 特性开关。
     */
    private function identifyByDomain(Request $request): ?string
    {
        // 检查多域名特性开关
        if (!config('tenant.features.multi_domain', false)) {
            return null;
        }

        $host = $request->host();

        if (empty($host)) {
            return null;
        }

        // 去除端口号（如 example.com:8080 → example.com）
        $host = preg_replace('/:\d+$/', '', $host);

        // 通过域名查找激活状态的租户
        $tenant = Tenant::findByDomain($host);

        return $tenant ? $tenant->id : null;
    }
}
